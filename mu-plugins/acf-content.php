<?php

add_action('init', function () {
    $settings_id = (int) get_option('npa_home_page_id');
    if ($settings_id && get_post_type($settings_id) === 'page') {
        return;
    }

    $existing = get_posts([
        'post_type'      => 'page',
        'post_status'    => ['publish', 'draft', 'pending', 'private'],
        'name'           => 'accueil',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);
    if ($existing) {
        update_option('npa_home_page_id', (int) $existing[0]);
        return;
    }

    $settings_id = wp_insert_post([
        'post_type'   => 'page',
        'post_status' => 'publish',
        'post_title'  => 'Accueil',
        'post_name'   => 'accueil',
        'post_content' => '',
    ]);
    if (is_wp_error($settings_id) || !$settings_id) {
        error_log('NPA: impossible de créer la page Accueil pour les champs ACF.');
        return;
    }
    update_option('npa_home_page_id', (int) $settings_id);
});

add_action('init', function () {
    $pages = [
        'contact'         => 'Contact',
        'mentions-legales' => 'Mentions légales',
        'cgv'             => 'Conditions générales de vente',
        'cgu'             => 'Conditions générales d’utilisation',
        'faq'             => 'Questions fréquentes',
    ];

    foreach ($pages as $slug => $title) {
        if (get_page_by_path($slug, OBJECT, 'page')) {
            continue;
        }

        $page_id = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => '',
        ], true);

        if (is_wp_error($page_id)) {
            error_log(sprintf('NPA: impossible de créer le brouillon de page "%s": %s', $slug, $page_id->get_error_message()));
        }
    }
});

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        return;
    }

    $text = function ($key, $label, $default = '') {
        return [
            'key'           => 'field_' . $key,
            'label'         => $label,
            'name'          => $key,
            'type'          => 'text',
            'default_value' => $default,
        ];
    };
    $textarea = function ($key, $label, $default = '', $rows = 3) {
        return [
            'key'           => 'field_' . $key,
            'label'         => $label,
            'name'          => $key,
            'type'          => 'textarea',
            'default_value' => $default,
            'rows'          => $rows,
            'new_lines'     => '',
        ];
    };
    $image = function ($key, $label) {
        return [
            'key'           => 'field_' . $key,
            'label'         => $label,
            'name'          => $key,
            'type'          => 'image',
            'return_format' => 'url',
            'preview_size'  => 'medium',
            'library'       => 'all',
        ];
    };

    $fields = [[
        'key'   => 'field_npa_site_tab_global',
        'label' => 'Coordonnées et textes globaux',
        'type'  => 'tab',
    ]];
    foreach ([
        ['global_site_name', 'Nom du site', 'NAB Pièces Auto'],
        ['global_tagline', 'Sous-titre du site', 'Comptoir Automobile Marseille 10e'],
        ['global_description', 'Description du magasin', "Votre spécialiste indépendant de la distribution de pièces détachées automobiles neuves d'origine et de qualité équivalente à Marseille et région Provence-Alpes-Côte d'Azur."],
        ['global_phone', 'Téléphone', '09 86 37 38 92'],
        ['global_whatsapp', 'WhatsApp', '07 61 99 29 84'],
        ['global_email', 'Adresse e-mail', 'contact@nabpiecesauto-marseille.fr'],
        ['global_address_street', 'Adresse du comptoir', '158 Avenue de la Capelette'],
        ['global_address_zip', 'Code postal', '13010'],
        ['global_address_city', 'Ville', 'Marseille'],
        ['global_maps_url', 'Lien Google Maps', 'https://www.google.com/maps/search/?api=1&query=158+Avenue+de+la+Capelette+13010+Marseille'],
        ['global_hours_weekdays', 'Horaires du lundi au vendredi', 'Lundi au Vendredi : 08h30 – 12h30 / 14h00 – 18h30'],
        ['global_hours_saturday', 'Horaires du samedi', 'Samedi : 09h00 – 13h00 (Comptoir & Retraits)'],
        ['global_hours_sunday', 'Horaires du dimanche', 'Dimanche : Fermé'],
        ['global_hours_short', 'Horaires condensés (bandeaux)', 'Lun - Ven : 08h30 - 12h30 / 14h00 - 18h30 • Sam : 09h00 - 13h00 • Parking gratuit'],
        ['global_access_note', 'Information d’accès', 'À 2 minutes de la sortie Rocade L2 Florian. Parking gratuit clients devant le magasin.'],
        ['global_topbar_1', 'Bandeau — message 1', 'Comptoir Capelette 13010 ouvert'],
        ['global_topbar_2', 'Bandeau — message 2', 'Tél : 09 86 37 38 92'],
        ['global_topbar_3', 'Bandeau — message 3', 'Livraison Pro express Marseille & PACA'],
        ['global_topbar_4', 'Bandeau — message 4', 'Retrait comptoir gratuit 2h'],
        ['global_topbar_highlight', 'Bandeau — mise en avant', 'Pièces Neuves Garanties Constructeur'],
    ] as $field) {
        $fields[] = $text(...$field);
    }
    $fields[] = $image('global_logo', 'Logo du site');

    $fields[] = [
        'key'   => 'field_npa_site_tab_home',
        'label' => 'Page d’accueil',
        'type'  => 'tab',
    ];
    $fields[] = $text('home_hero_badge', 'Accroche du bandeau principal', 'Comptoir stock permanent • 13010 Marseille');
    $fields[] = $text('home_hero_title', 'Titre principal', 'Vos pièces auto au meilleur prix immédiatement');
    $fields[] = $textarea('home_hero_description', 'Texte principal', 'Plus de 150 000 références neuves certifiées constructeur en stock ou disponibles sous 2h à notre comptoir de La Capelette. Commandez en ligne, retirez sur place ou faites-vous livrer sur Marseille & alentours.', 4);
    $fields[] = $image('home_hero_image', 'Image du bandeau principal');
    $fields[] = $textarea('home_hero_features', 'Arguments du bandeau (un par ligne)', "Dispo en 2h\n100% neuves OEM\nConseil d'atelier", 3);
    $fields[] = $text('home_families_eyebrow', 'Catalogue — petit titre', 'Catalogue express');
    $fields[] = $text('home_families_title', 'Catalogue — titre', 'Les grandes familles en stock');
    $fields[] = $textarea('home_families_description', 'Catalogue — description', 'Références de première monte certifiées disponibles immédiatement au comptoir ou en livraison rapide.', 3);
    $family_defaults = [
        ['Freinage', 'Disques, Plaquettes'],
        ['Filtration', 'Huile, Air, Gazole'],
        ['Distribution', 'Kits & Pompes à eau'],
        ['Embrayage', 'Kits & Volants bimasse'],
        ['Suspension', 'Amortisseurs & Bras'],
        ['Huiles & fluides', '5W30, 0W20, DOT'],
    ];
    foreach ($family_defaults as $index => $defaults) {
        $number = $index + 1;
        $fields[] = $text("home_family_{$number}_title", "Famille {$number} — titre", $defaults[0]);
        $fields[] = $text("home_family_{$number}_description", "Famille {$number} — description", $defaults[1]);
    }
    $fields[] = $text('home_pro_title', 'Espace professionnels — titre', 'Espace pros & garages PACA');
    $fields[] = $text('home_pro_tag', 'Espace professionnels — étiquette', 'Tarifs ateliers');
    $fields[] = $textarea('home_pro_description', 'Espace professionnels — description', 'Navettes quotidiennes express, compte avec facturation mensuelle et remises directes.', 3);
    $reassurance_defaults = [
        ['Retrait 2h Capelette', 'Comptoir 158 Av. Capelette, gratuit'],
        ['Livraison 24/48h', 'Garages & particuliers sur toute la région'],
        ['100% neuves OEM', 'Qualité première monte certifiée'],
        ['Conseils experts', 'Support marseillais au 09 86 37 38 92'],
    ];
    foreach ($reassurance_defaults as $index => $defaults) {
        $number = $index + 1;
        $fields[] = $text("home_reassurance_{$number}_title", "Engagement {$number} — titre", $defaults[0]);
        $fields[] = $text("home_reassurance_{$number}_description", "Engagement {$number} — description", $defaults[1]);
    }
    $fields[] = $text('contact_success_title', 'Contact — titre après envoi', 'Message envoyé');
    $fields[] = $textarea('contact_success_text', 'Contact — message après envoi', 'Merci, notre équipe du comptoir Capelette vous répond au plus vite.', 3);

    acf_add_local_field_group([
        'key'                   => 'group_npa_site_content',
        'title'                 => 'Contenus du site NPA',
        'fields'                => $fields,
        'location'              => [[[
            'param'    => 'page',
            'operator' => '==',
            'value'    => (string) get_option('npa_home_page_id'),
        ]]],
        'menu_order'            => 0,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
    ]);

    acf_add_local_field_group([
        'key'   => 'group_npa_product_content',
        'title' => 'Informations complémentaires NPA',
        'fields' => [
            $text('npa_product_subtitle', 'Sous-titre de la fiche produit'),
            $textarea('npa_product_fitment_note', 'Note de compatibilité', '', 3),
            $image('npa_product_brand_logo', 'Logo de marque personnalisé'),
        ],
        'location' => [[[
            'param'    => 'post_type',
            'operator' => '==',
            'value'    => 'product',
        ]]],
        'position'        => 'normal',
        'style'           => 'default',
        'label_placement' => 'top',
        'active'          => true,
    ]);
});

add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/site-content', [
        'methods'             => 'GET',
        'callback'            => function () {
            $settings_id = (int) get_option('npa_home_page_id');
            $fields = function_exists('get_fields') && $settings_id
                ? (get_fields($settings_id) ?: [])
                : [];
            $public_fields = [
                'global_site_name', 'global_tagline', 'global_description',
                'global_phone', 'global_whatsapp', 'global_email',
                'global_address_street', 'global_address_zip', 'global_address_city',
                'global_maps_url', 'global_hours_weekdays', 'global_hours_saturday',
                'global_hours_sunday', 'global_hours_short', 'global_access_note', 'global_topbar_1',
                'global_topbar_2', 'global_topbar_3', 'global_topbar_4',
                'global_topbar_highlight', 'global_logo', 'home_hero_badge',
                'home_hero_title', 'home_hero_description', 'home_hero_image',
                'home_hero_features', 'home_families_eyebrow', 'home_families_title',
                'home_families_description', 'home_pro_title', 'home_pro_tag',
                'home_pro_description', 'contact_success_title', 'contact_success_text',
            ];
            foreach (range(1, 6) as $index) {
                $public_fields[] = "home_family_{$index}_title";
                $public_fields[] = "home_family_{$index}_description";
            }
            foreach (range(1, 4) as $index) {
                $public_fields[] = "home_reassurance_{$index}_title";
                $public_fields[] = "home_reassurance_{$index}_description";
            }
            $fields = array_intersect_key($fields, array_flip($public_fields));

            return rest_ensure_response(['fields' => $fields]);
        },
        'permission_callback' => '__return_true',
    ]);
});
