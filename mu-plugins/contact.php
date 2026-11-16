<?php

/**
 * Formulaire de contact (route POST /custom/v1/contact) :
 * - valide et nettoie chaque champ côté serveur (on ne fait jamais confiance au navigateur) ;
 * - anti-spam : champ piège invisible + 5 envois par heure et par adresse IP ;
 * - enregistre le message dans l'admin (menu "Messages contact") ;
 * - prévient le comptoir par e-mail (adresse d'expédition réglée dans WooCommerce → Réglages → E-mails).
 */

const NPA_CONTACT_SUBJECTS = [
    'piece'     => 'Question sur une pièce',
    'devis'     => 'Demande de devis / identification VIN',
    'pro'       => 'Compte professionnel',
    'commande'  => 'Suivi de commande',
    'autre'     => 'Autre demande',
];

// Messages enregistrés dans l'admin, visibles uniquement par les administrateurs
add_action('init', function () {
    register_post_type('npa_contact', [
        'labels'          => [
            'name'          => 'Messages contact',
            'singular_name' => 'Message contact',
            'all_items'     => 'Tous les messages',
            'edit_item'     => 'Message',
            'search_items'  => 'Rechercher un message',
            'not_found'     => 'Aucun message',
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_rest'    => false,
        'menu_icon'       => 'dashicons-email-alt',
        'menu_position'   => 26,
        'supports'        => ['title', 'editor'],
        'capability_type' => 'post',
        'capabilities'    => ['create_posts' => 'do_not_allow'], // créés seulement par le formulaire
        'map_meta_cap'    => true,
    ]);
});

function npa_contact_unread_count()
{
    global $wpdb;

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
         WHERE p.post_type = %s AND p.post_status IN (%s, %s)
         AND NOT EXISTS (
             SELECT 1 FROM {$wpdb->postmeta} m
             WHERE m.post_id = p.ID AND m.meta_key = %s
         )",
        'npa_contact',
        'private',
        'draft',
        '_npa_contact_read'
    ));

    if ($count === null) {
        error_log('NPA contact : impossible de compter les messages non lus : ' . $wpdb->last_error);
        return null;
    }

    return (int) $count;
}

function npa_contact_update_menu_badge()
{
    global $menu;

    $type = get_post_type_object('npa_contact');
    if (!$type || !current_user_can($type->cap->edit_posts) || !current_user_can($type->cap->read_private_posts)) {
        return;
    }

    $count = npa_contact_unread_count();
    if ($count === null) {
        return;
    }

    foreach ($menu as &$item) {
        if ($item[2] !== 'edit.php?post_type=npa_contact') {
            continue;
        }
        $item[0] = esc_html($type->labels->name);
        if ($count > 0) {
            $label = sprintf(_n('%s message non lu', '%s messages non lus', $count), number_format_i18n($count));
            $item[0] .= ' <span class="awaiting-mod count-' . esc_attr($count) . '"><span aria-hidden="true">'
                . esc_html(number_format_i18n($count)) . '</span><span class="screen-reader-text">'
                . esc_html($label) . '</span></span>';
        }
        break;
    }
    unset($item);
}
add_action('admin_menu', 'npa_contact_update_menu_badge', 999);

function npa_contact_mark_opened_read()
{
    $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if ($action !== 'edit' || !$post_id || get_post_type($post_id) !== 'npa_contact'
        || !in_array(get_post_status($post_id), ['private', 'draft'], true) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!metadata_exists('post', $post_id, '_npa_contact_read')
        && !add_post_meta($post_id, '_npa_contact_read', '1', true)
        && !metadata_exists('post', $post_id, '_npa_contact_read')) {
        error_log('NPA contact : impossible de marquer le message ' . $post_id . ' comme lu.');
    }

    npa_contact_update_menu_badge();
}
add_action('load-post.php', 'npa_contact_mark_opened_read');

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/contact', [
        'methods'             => 'POST',
        'callback'            => 'npa_contact_submit',
        'permission_callback' => '__return_true',
    ]);
});

function npa_contact_rate_limit_check()
{
    $ip  = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = 'npa_contact_' . md5($ip);
    $attempts = (int) get_transient($key);
    if ($attempts >= 5) {
        return false;
    }
    set_transient($key, $attempts + 1, HOUR_IN_SECONDS);
    return true;
}

function npa_contact_submit(WP_REST_Request $request)
{
    // Champ piège : invisible pour un humain, rempli par les robots.
    // On répond "envoyé" pour ne pas leur indiquer qu'ils sont détectés.
    if (trim((string) $request->get_param('website')) !== '') {
        return rest_ensure_response(['sent' => true]);
    }

    if (!npa_contact_rate_limit_check()) {
        return new WP_Error('too_many_requests', 'Trop de messages envoyés. Réessayez plus tard ou appelez le comptoir.', ['status' => 429]);
    }

    $name    = sanitize_text_field((string) $request->get_param('name'));
    $email   = sanitize_email((string) $request->get_param('email'));
    $phone   = sanitize_text_field((string) $request->get_param('phone'));
    $subject = sanitize_key((string) $request->get_param('subject'));
    $vehicle = strtoupper(sanitize_text_field((string) $request->get_param('vehicle')));
    $message = sanitize_textarea_field((string) $request->get_param('message'));
    $consent = rest_sanitize_boolean($request->get_param('consent'));

    $errors = [];
    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Indiquez votre nom (100 caractères maximum).';
    }
    if (!is_email($email)) {
        $errors['email'] = 'Adresse e-mail invalide.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+\s.\-()]{6,20}$/', $phone)) {
        $errors['phone'] = 'Numéro de téléphone invalide.';
    }
    if (!array_key_exists($subject, NPA_CONTACT_SUBJECTS)) {
        $errors['subject'] = 'Choisissez un sujet.';
    }
    // Immatriculation AB-123-CD ou VIN à 17 caractères (sans I, O ni Q)
    $vehicleCompact = str_replace([' ', '-'], '', $vehicle);
    if ($vehicle !== '' && !preg_match('/^([A-Z]{2}\d{3}[A-Z]{2}|[A-HJ-NPR-Z0-9]{17})$/', $vehicleCompact)) {
        $errors['vehicle'] = 'Immatriculation (AB-123-CD) ou VIN (17 caractères) invalide.';
    }
    if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
        $errors['message'] = 'Votre message doit contenir entre 10 et 3000 caractères.';
    }
    if (!$consent) {
        $errors['consent'] = 'Vous devez accepter le traitement de vos données pour envoyer le message.';
    }
    if ($errors) {
        return new WP_Error('invalid_fields', 'Certains champs sont invalides.', ['status' => 400, 'fields' => $errors]);
    }

    $subjectLabel = NPA_CONTACT_SUBJECTS[$subject];
    $body = implode("\n", array_filter([
        "Nom : $name",
        "E-mail : $email",
        $phone !== '' ? "Téléphone : $phone" : null,
        "Sujet : $subjectLabel",
        $vehicle !== '' ? "Immatriculation / VIN : $vehicle" : null,
        '',
        $message,
    ], fn($line) => $line !== null));

    // 1. Enregistrement dans l'admin (le message n'est jamais perdu, même si l'e-mail échoue)
    wp_insert_post([
        'post_type'    => 'npa_contact',
        'post_status'  => 'private',
        'post_title'   => "$subjectLabel – $name",
        'post_content' => $body,
    ]);

    // 2. E-mail au comptoir, en texte brut. L'adresse du visiteur ne sert qu'au "Répondre"
    // (validée par is_email ci-dessus, sans retour à la ligne possible).
    $to = get_option('woocommerce_email_from_address') ?: get_option('admin_email');
    wp_mail(
        $to,
        "[Site] $subjectLabel – $name",
        $body,
        ['Content-Type: text/plain; charset=UTF-8', "Reply-To: $email"]
    );

    return rest_ensure_response(['sent' => true]);
}
