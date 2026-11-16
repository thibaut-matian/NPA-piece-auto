<?php

if (PHP_SAPI !== 'cli') {
    exit("CLI only.\n");
}

$wordpress_path = rtrim($argv[1] ?? '', '/\\');
if (!$wordpress_path || !is_file($wordpress_path . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php scripts/test-product-tax.php WORDPRESS_PATH\n");
    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $wordpress_path . '/wp-load.php';

function assert_product_tax($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}

$temporary_products = [];
$failed = false;
try {
    assert_product_tax(
        in_array(wp_parse_url(home_url(), PHP_URL_HOST), ['localhost', '127.0.0.1'], true),
        'Local WordPress only.'
    );
    assert_product_tax(wc_prices_include_tax(), 'Prices entered including tax.');
    WC()->initialize_session();
    WC()->customer = new WC_Customer();
    WC()->customer->set_billing_country(WC()->countries->get_base_country());
    WC()->customer->set_shipping_country(WC()->countries->get_base_country());

    $simple = new WC_Product_Simple();
    $simple->set_regular_price('9.90');
    $simple->set_price('9.90');
    $simple->set_tax_status('taxable');
    assert_product_tax(headless_product_price_excluding_tax($simple) === '825', '9.90 TTC gives 8.25 HT at the local standard rate.');
    $simple->set_tax_status('none');
    assert_product_tax(headless_product_price_excluding_tax($simple) === '990', 'Untaxed product is not divided by a fixed rate.');
    $simple->set_price('0');
    assert_product_tax(headless_product_price_excluding_tax($simple) === '0', 'Free product keeps a zero price.');
    $simple->set_price('');
    assert_product_tax(headless_product_price_excluding_tax($simple) === null, 'Missing price is not replaced with zero.');

    $variable = new WC_Product_Variable();
    $variable->set_name('Temporary local tax test');
    $variable->set_status('publish');
    $temporary_products[] = $variable;
    $variable->save();
    foreach (['12.00', '11.00'] as $price) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($variable->get_id());
        $variation->set_status('publish');
        $variation->set_regular_price($price);
        $variation->set_price($price);
        $temporary_products[] = $variation;
        $variation->save();
    }
    WC_Product_Variable::sync($variable->get_id());
    $variable = wc_get_product($variable->get_id());
    assert_product_tax(headless_product_price_excluding_tax($variable) === '917', 'Variable HT matches the cheapest displayed TTC variation with currency rounding.');

    $request = new WP_REST_Request('GET', '/custom/v1/products/' . $variable->get_id());
    $response = rest_do_request($request);
    assert_product_tax(!$response->is_error(), 'Product detail API succeeds.');
    $data = json_decode(wp_json_encode($response->get_data()), true);
    assert_product_tax($data['npa']['price_excluding_tax'] === '917', 'API exposes HT in minor currency units.');
    assert_product_tax((string) $data['prices']['price_range']['min_amount'] === '1100', 'API TTC and HT refer to the same variation.');

    $children = [];
    foreach ([['12.00', 'taxable'], ['11.00', 'none']] as [$price, $tax_status]) {
        $child = new WC_Product_Simple();
        $child->set_name('Temporary grouped tax test');
        $child->set_status('publish');
        $child->set_regular_price($price);
        $child->set_price($price);
        $child->set_tax_status($tax_status);
        $temporary_products[] = $child;
        $children[] = $child->save();
    }
    $grouped = new WC_Product_Grouped();
    $grouped->set_children($children);
    assert_product_tax(headless_product_price_excluding_tax($grouped) === '1100', 'Grouped HT refers to the cheapest TTC child, not the cheapest HT.');
} catch (Throwable $error) {
    fwrite(STDERR, "FAIL: " . $error->getMessage() . "\n");
    $failed = true;
} finally {
    foreach (array_reverse($temporary_products) as $product) {
        if ($product->get_id()) {
            $product->delete(true);
        }
    }
    exit($failed ? 1 : 0);
}
