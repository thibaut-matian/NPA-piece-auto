<?php

add_action('init', function () {
    register_post_status('wc-npa-ready', [
        'label'                     => 'Prête à retirer',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Prête à retirer (%s)', 'Prêtes à retirer (%s)'),
    ]);
});

add_filter('wc_order_statuses', function ($statuses) {
    $statuses['wc-npa-ready'] = 'Prête à retirer';
    return $statuses;
});

add_filter('woocommerce_order_is_paid', function ($paid, $order) {
    return $order->has_status('npa-ready') ? (bool) $order->get_date_paid() : $paid;
}, 10, 2);

function npa_is_pickup_order($order)
{
    $methods = $order->get_shipping_methods();
    return count($methods) > 0 && count(array_filter($methods, function ($method) {
        return $method->get_method_id() !== 'local_pickup';
    })) === 0;
}

function npa_pickup_details($order)
{
    if (!$order->has_status('npa-ready') || !npa_is_pickup_order($order)) {
        return null;
    }
    $ready = $order->get_meta('_npa_pickup_ready_at');
    $deadline = $order->get_meta('_npa_pickup_deadline');
    if (!$ready || !$deadline || strtotime($ready) === false || strtotime($deadline) === false) {
        wc_get_logger()->error('Dates de retrait manquantes ou invalides.', ['source' => 'npa-pickup', 'order_id' => $order->get_id()]);
        return null;
    }
    return [
        'ready_at'       => $ready,
        'deadline'       => $deadline,
        'deadline_label' => wp_date('d/m/Y à H:i', strtotime($deadline)),
    ];
}

add_action('woocommerce_before_order_object_save', function ($order) {
    if (!$order instanceof WC_Order || !$order->has_status('npa-ready')) {
        return;
    }
    $previous = $order->get_id() ? wc_get_order($order->get_id()) : false;
    if (!npa_is_pickup_order($order) || !$previous || !$previous->has_status(['processing', 'on-hold', 'npa-ready'])) {
        $message = 'Retrait refusé : seules les commandes en préparation ou en attente avec retrait magasin peuvent être marquées « Prête à retirer ».';
        $fallback = $previous ? $previous->get_status() : 'pending';
        $order->set_status($fallback === 'npa-ready' ? 'on-hold' : $fallback);
        if ($order->get_id()) {
            $order->add_order_note($message);
        }
        wc_get_logger()->warning($message, ['source' => 'npa-pickup', 'order_id' => $order->get_id()]);
        if (class_exists('WC_Admin_Meta_Boxes')) {
            WC_Admin_Meta_Boxes::add_error($message);
        }
        return;
    }

    // Une réouverture ou un renvoi de l'e-mail ne prolonge pas les sept jours.
    if (!$order->get_meta('_npa_pickup_ready_at')) {
        $ready = new DateTimeImmutable('now', wp_timezone());
        $order->update_meta_data('_npa_pickup_ready_at', $ready->format(DATE_ATOM));
        $order->update_meta_data('_npa_pickup_deadline', $ready->modify('+7 days')->format(DATE_ATOM));
    }
});

add_filter('woocommerce_email_actions', function ($actions) {
    $actions[] = 'woocommerce_order_status_npa-ready';
    return $actions;
});

add_filter('woocommerce_email_classes', function ($emails) {
    require_once __DIR__ . '/includes/class-npa-pickup-email.php';
    $emails['NPA_Pickup_Email'] = new NPA_Pickup_Email();
    return $emails;
});

add_filter('woocommerce_order_actions', function ($actions, $order) {
    if ($order instanceof WC_Order && npa_pickup_details($order)) {
        $actions['npa_resend_pickup_ready'] = 'Renvoyer l’e-mail « Prête à retirer »';
    }
    return $actions;
}, 10, 2);

add_action('woocommerce_order_action_npa_resend_pickup_ready', function ($order) {
    if (npa_pickup_details($order)) {
        WC()->mailer()->get_emails()['NPA_Pickup_Email']->trigger($order->get_id(), $order);
    }
});

add_action('woocommerce_admin_order_data_after_shipping_address', function ($order) {
    $pickup = npa_pickup_details($order);
    if ($pickup && $pickup['deadline']) {
        echo '<p><strong>Retrait avant le :</strong> ' . esc_html(
            wp_date('d/m/Y à H:i', strtotime($pickup['deadline']))
        ) . '<br>Aucune annulation automatique après cette date.</p>';
    }
});
