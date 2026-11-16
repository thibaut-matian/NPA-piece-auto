<?php

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$wordpress_path = rtrim($argv[1] ?? '', '/\\');
if (!$wordpress_path || !is_file($wordpress_path . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/test-pickup.php LOCAL_WORDPRESS_PATH\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $wordpress_path . '/wp-load.php';

function assert_pickup_test($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$orders = [];
$mails = [];
$fail_mail = false;
// Capture every message, including standard WooCommerce notifications.
add_filter('pre_wp_mail', function ($result, $attributes) use (&$mails, &$fail_mail) {
    $mails[] = $attributes;
    return !$fail_mail;
}, PHP_INT_MAX, 2);

try {
    assert_pickup_test(in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true), 'Run only on local WordPress.');
    assert_pickup_test(function_exists('npa_pickup_details'), 'Install the pickup mu-plugin in local WordPress first.');
    WC()->mailer();
    // Test the synchronous notification path even when deferred delivery is enabled locally.
    foreach (array_keys($GLOBALS['wp_filter']) as $action) {
        if (has_action($action, ['WC_Emails', 'queue_transactional_email']) !== false) {
            remove_action($action, ['WC_Emails', 'queue_transactional_email'], 10);
            add_action($action, ['WC_Emails', 'send_transactional_email'], 10, 10);
        }
    }
    $email = WC()->mailer()->get_emails()['NPA_Pickup_Email'];
    $email->enabled = 'yes';
    $email->settings['pickup_address'] = '158 Avenue de la Capelette, 13010 Marseille';
    assert_pickup_test(isset(wc_get_order_statuses()['wc-npa-ready']), 'Status missing.');

    $make_order = function ($method, $payment = 'cod') use (&$orders) {
        $order = wc_create_order();
        if (is_wp_error($order)) {
            throw new RuntimeException($order->get_error_message());
        }
        $orders[] = $order;
        $order->set_billing_email('qa-pickup@example.invalid');
        $order->set_payment_method($payment);
        $shipping = new WC_Order_Item_Shipping();
        $shipping->set_method_id($method);
        $shipping->set_method_title('Test pickup');
        $order->add_item($shipping);
        $order->update_status('on-hold');
        return $order;
    };

    $order = $make_order('local_pickup');
    $mails = [];
    $order->update_status('npa-ready');
    $pickup = npa_pickup_details(wc_get_order($order->get_id()));
    assert_pickup_test($pickup && $pickup['ready_at'] && $pickup['deadline'], 'Dates not persisted.');
    $ready = new DateTimeImmutable($pickup['ready_at']);
    $deadline = new DateTimeImmutable($pickup['deadline']);
    assert_pickup_test($deadline == $ready->modify('+7 days'), 'Deadline is not seven calendar days.');
    assert_pickup_test(count($mails) === 1, 'Expected exactly one ready email.');
    assert_pickup_test(str_contains($mails[0]['message'], wp_date('d/m/Y à H:i', $deadline->getTimestamp())), 'Deadline missing from HTML email.');
    assert_pickup_test(str_contains($mails[0]['message'], '158 Avenue de la Capelette, 13010 Marseille'), 'Store address missing.');
    assert_pickup_test(str_contains($mails[0]['message'], 'reste à régler'), 'Unpaid pickup instructions missing.');
    assert_pickup_test(!$order->get_date_paid(), 'Ready status must not mark COD paid.');
    assert_pickup_test(!$order->is_paid(), 'COD pickup considered paid by WooCommerce.');
    assert_pickup_test(str_contains($email->get_content_plain(), '7 jours calendaires'), 'Plain email missing.');
    echo "PASS: ready status, persisted deadline, HTML/plain email, unpaid COD\n";

    $order->save();
    assert_pickup_test(count($mails) === 1, 'Saving a ready order resent the email.');
    do_action('woocommerce_order_action_npa_resend_pickup_ready', $order);
    assert_pickup_test(count($mails) === 2, 'Manual resend failed.');
    assert_pickup_test(npa_pickup_details($order) === $pickup, 'Resend extended the deadline.');
    $order->update_status('on-hold');
    $order->update_status('npa-ready');
    assert_pickup_test(npa_pickup_details($order) === $pickup, 'Reopening extended the deadline.');
    echo "PASS: ordinary save sends no duplicate; manual resend and reopening preserve dates\n";

    $paid = $make_order('local_pickup', 'stripe');
    $paid->set_date_paid(time() - 60);
    $paid->update_status('processing');
    $date_paid = $paid->get_date_paid()->getTimestamp();
    $paid->update_status('npa-ready');
    assert_pickup_test($paid->get_date_paid()->getTimestamp() === $date_paid, 'Paid order lost its payment date.');
    assert_pickup_test($paid->is_paid(), 'Paid pickup no longer considered paid by WooCommerce.');
    echo "PASS: payment date preserved for already-paid pickup\n";

    $delivery = $make_order('flat_rate');
    $count = count($mails);
    $delivery->update_status('npa-ready');
    assert_pickup_test(wc_get_order($delivery->get_id())->has_status('on-hold'), 'Home delivery accepted as pickup.');
    assert_pickup_test(npa_pickup_details($delivery) === null, 'Home delivery exposes pickup dates.');
    assert_pickup_test(count($mails) === $count, 'Rejected home delivery sent an email.');
    $pending = wc_create_order();
    assert_pickup_test(!is_wp_error($pending), 'Cannot create pending test order.');
    $orders[] = $pending;
    $pending->update_status('npa-ready');
    assert_pickup_test(wc_get_order($pending->get_id())->has_status('pending'), 'Unprepared order accepted.');
    $cancelled = $make_order('local_pickup');
    $cancelled->update_status('cancelled');
    $cancelled->update_status('npa-ready');
    assert_pickup_test(wc_get_order($cancelled->get_id())->has_status('cancelled'), 'Cancelled order accepted as ready.');
    echo "PASS: home delivery and unprepared orders rejected\n";

    $fail_mail = true;
    $count = count($mails);
    do_action('woocommerce_order_action_npa_resend_pickup_ready', $order);
    assert_pickup_test(count($mails) === $count + 1, 'Failure path not exercised.');
    $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
    assert_pickup_test((bool) array_filter($notes, fn($note) => str_contains($note->content, 'Échec de l’envoi')), 'Email failure was not recorded.');
    $fail_mail = false;
    $email->enabled = 'no';
    $count = count($mails);
    do_action('woocommerce_order_action_npa_resend_pickup_ready', $order);
    assert_pickup_test(count($mails) === $count, 'Disabled email was sent.');
    $email->enabled = 'yes';
    $without_email = $make_order('local_pickup');
    $without_email->set_billing_email('');
    $count = count($mails);
    $without_email->update_status('npa-ready');
    assert_pickup_test(count($mails) === $count, 'Sent a message without a recipient.');
    $notes = wc_get_order_notes(['order_id' => $without_email->get_id()]);
    assert_pickup_test((bool) array_filter($notes, fn($note) => str_contains($note->content, 'Échec de l’envoi')), 'Missing recipient was not reported.');
    echo "PASS: delivery failure logged; disabled notification respected\n";

    $request = new WP_REST_Request('GET', '/custom/v1/order-confirmation/' . $order->get_id());
    $request->set_param('key', $order->get_order_key());
    $response = rest_do_request($request);
    assert_pickup_test($response->get_status() === 200 && $response->get_data()['pickup'] === $pickup, 'Confirmation API missing dates.');
    assert_pickup_test($response->get_data()['is_paid'] === false, 'COD pickup incorrectly marked paid in API.');
    $request->set_param('key', 'invalid');
    assert_pickup_test(rest_do_request($request)->get_status() === 404, 'Order details accessible with invalid key.');
    $users = get_users(['fields' => 'ID', 'number' => 1]);
    assert_pickup_test(!empty($users), 'A local account is required for history checks.');
    $order->set_customer_id($users[0]);
    $order->save();
    wp_set_current_user($users[0]);
    $response = rest_do_request(new WP_REST_Request('GET', '/custom/v1/orders'));
    $history = array_values(array_filter($response->get_data(), fn($item) => $item['id'] === $order->get_id()));
    assert_pickup_test($response->get_status() === 200 && ($history[0]['pickup'] ?? null) === $pickup, 'Customer history missing pickup details.');
    $request->set_param('key', '');
    assert_pickup_test(rest_do_request($request)->get_status() === 200, 'Owner cannot access confirmation.');
    $request = new WP_REST_Request('GET', '/wc/store/v1/order/' . $order->get_id());
    $request->set_param('key', $order->get_order_key());
    $request->set_param('billing_email', $order->get_billing_email());
    $response = rest_do_request($request);
    assert_pickup_test($response->get_status() === 200, 'Store API order unavailable.');
    wp_set_current_user(0);
    echo "PASS: authorized confirmation and customer history expose dates; existing Store API still works\n";

    $order->update_meta_data('_npa_pickup_deadline', (new DateTimeImmutable('-1 day', wp_timezone()))->format(DATE_ATOM));
    $order->save();
    assert_pickup_test(wc_get_order($order->get_id())->has_status('npa-ready'), 'Expired deadline changed status.');
    $order->update_status('completed');
    assert_pickup_test(npa_pickup_details($order) === null, 'Completed order still advertised as ready.');
    echo "PASS: no automatic expiry cancellation; completed pickup no longer shown as ready\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $failed = true;
} finally {
    foreach ($orders as $order) {
        $order->delete(true);
    }
}

exit(isset($failed) ? 1 : 0);
