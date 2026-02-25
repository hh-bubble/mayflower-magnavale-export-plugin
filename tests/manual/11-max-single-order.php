#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 11: Maximum Single Order
 * Creates 1 order containing every single exportable product in the catalog.
 * Tests completeness: every SKU should appear in the CSV.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/11-max-single-order.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 11: Maximum Single Order (Every Product) ===" . PHP_EOL;

$customer = MME_TEST_CUSTOMERS[2]; // Wei Zhang
$all_products = mme_test_get_all_products();

// Add every product with qty 1
$items = [];
foreach ( $all_products as $product ) {
    $items[] = [ 'id' => $product['id'], 'qty' => 1 ];
}

$total_products = count( $items );
echo "Adding {$total_products} distinct products to a single order..." . PHP_EOL;

$order_id = mme_test_create_order( $customer, $items, [
    'note' => "Test 11: Every product in catalog ({$total_products} products, {$total_products} pieces)"
] );

if ( $order_id ) {
    echo "SUCCESS: Order #{$order_id} created ({$total_products} line items, {$total_products} pieces)" . PHP_EOL;
    echo "Verify: Order CSV should have exactly {$total_products} rows for this order." . PHP_EOL;
    echo "Verify: Every Magnavale SKU should appear in the output." . PHP_EOL;
    echo "Verify: Packing CSV should list all {$total_products} products plus packaging materials." . PHP_EOL;

    // Print expected SKUs for manual verification
    echo PHP_EOL . "Expected SKUs:" . PHP_EOL;
    foreach ( $all_products as $p ) {
        echo "  {$p['sku']} - {$p['name']}" . PHP_EOL;
    }
} else {
    echo "FAILED: Could not create order." . PHP_EOL;
    exit( 1 );
}
