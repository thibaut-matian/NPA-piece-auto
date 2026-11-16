<?php

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$wordpress_path = rtrim($argv[1] ?? '', '/\\');
$product_id = (int) ($argv[2] ?? 0);
if (!$wordpress_path || !is_file($wordpress_path . '/wp-load.php') || !$product_id) {
    fwrite(STDERR, "Usage: php scripts/test-cart-shipping.php WORDPRESS_PATH PRODUCT_ID\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $wordpress_path . '/wp-load.php';

function assert_shipping_test($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    assert_shipping_test(class_exists('WooCommerce'), 'WooCommerce is required.');
    $product = wc_get_product($product_id);
    assert_shipping_test(
        $product && $product->is_type('simple') && $product->is_purchasable()
        && $product->needs_shipping() && $product->is_taxable(),
        'Use a purchasable, simple, physical and taxable test product.'
    );
    assert_shipping_test(wc_prices_include_tax(), 'This test requires prices entered including tax.');
    WC()->initialize_session();
    WC()->initialize_cart();

    $user_ids = get_users(['fields' => 'ID', 'number' => 1]);
    assert_shipping_test(!empty($user_ids), 'An existing local account is required.');
    $base_country = WC()->countries->get_base_country();
    $foreign_country = $base_country === 'BE' ? 'DE' : 'BE';

    foreach (['guest', 'account-without-country', 'saved-domestic-address', 'saved-foreign-address'] as $scenario) {
        $customer = new WC_Customer($scenario === 'guest' ? 0 : $user_ids[0]);
        $country = $scenario === 'saved-foreign-address' ? $foreign_country
            : ($scenario === 'saved-domestic-address' ? $base_country : '');
        $postcode = str_starts_with($scenario, 'saved-') ? '1000' : '';
        $customer->set_shipping_country($country);
        $customer->set_billing_country($country);
        $customer->set_shipping_postcode($postcode);
        $customer->set_billing_postcode($postcode);
        $customer->set_is_vat_exempt(false);
        WC()->customer = $customer;
        WC()->cart = new WC_Cart();
        WC()->session->set('chosen_shipping_methods', []);
        WC()->session->__unset('shipping_for_package_0');
        WC()->shipping()->reset_shipping();

        assert_shipping_test($customer->get_shipping_country() === ($country ?: $base_country), "$scenario: shipping country.");
        assert_shipping_test($customer->get_billing_country() === ($country ?: $base_country), "$scenario: billing country.");
        assert_shipping_test($customer->get_shipping_country('edit') === $country, "$scenario: raw country changed.");
        if ($postcode !== '') {
            assert_shipping_test($customer->get_shipping_postcode() === $postcode, "$scenario: postcode overwritten.");
        }

        if ($scenario !== 'saved-foreign-address') {
            assert_shipping_test((bool) WC()->cart->add_to_cart($product_id, 1), "$scenario: failed to add product.");
            WC()->cart->calculate_totals();
            $rates = [];
            foreach (WC()->shipping()->get_packages() as $package) {
                $rates = array_merge($rates, array_values($package['rates']));
            }
            $pickups = array_filter($rates, function ($rate) {
                return $rate->get_method_id() === 'local_pickup'
                    && (float) $rate->get_cost() === 0.0;
            });
            assert_shipping_test(!empty($pickups), "$scenario: free pickup missing.");
            assert_shipping_test(WC()->cart->get_total_tax() > 0, "$scenario: tax missing.");
            assert_shipping_test(
                abs((float) WC()->cart->get_total('edit') - (float) $product->get_price()) < 0.01,
                "$scenario: pickup total does not match the tax-inclusive product price."
            );
        } else {
            $customer->set_shipping_postcode('');
            assert_shipping_test($customer->get_shipping_postcode() === '', 'Foreign postcode replaced by store postcode.');
        }
        echo "PASS: $scenario\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: " . $error->getMessage() . "\n");
    exit(1);
}
