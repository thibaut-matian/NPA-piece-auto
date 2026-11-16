<?php

defined('ABSPATH') || exit;

add_action('phpmailer_init', function ($mailer) {
    if (!in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1', '::1'], true)) {
        return;
    }

    $mailer->isSMTP();
    $mailer->Host = '127.0.0.1';
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPSecure = '';
    $mailer->SMTPAutoTLS = false;
}, PHP_INT_MAX);
