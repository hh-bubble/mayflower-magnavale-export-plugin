#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 08: Stress Test — 20 Orders
 * Creates 20 orders with randomised products (2-6 items each).
 * Tests realistic peak-day volume.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/08-stress-test-20-orders.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 08: Stress Test — 20 Orders ===" . PHP_EOL;

$start_time = microtime( true );
$all_products = mme_test_get_all_products();
$customers = MME_TEST_CUSTOMERS;
$target = 20;
$created = [];
$total_items = 0;

for ( $i = 1; $i <= $target; $i++ ) {
    $customer = $customers[ array_rand( $customers ) ];
    $num_products = rand( 2, 6 );
    $items = mme_test_pick_products( $all_products, $num_products, 1, 4 );
    $pieces = array_sum( array_column( $items, 'qty' ) );
    $total_items += $pieces;
    $name = 'TEST-' . $customer['first'] . ' ' . $customer['last'];

    echo "  [{$i}/{$target}] {$name} ({$num_products} products, {$pieces} pieces)..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "Stress test 08: Order {$i} of {$target}"
    ] );

    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

$elapsed = round( microtime( true ) - $start_time, 2 );
$mem = round( memory_get_peak_usage( true ) / 1024 / 1024, 1 );

echo PHP_EOL;
echo "DONE: " . count( $created ) . "/{$target} orders created in {$elapsed}s" . PHP_EOL;
echo "Total items across all orders: {$total_items}" . PHP_EOL;
echo "Peak memory: {$mem} MB" . PHP_EOL;
echo "Order IDs: " . implode( ', ', $created ) . PHP_EOL;
