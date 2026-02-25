#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 09: Stress Test — 50 Orders
 * Creates 50 orders with randomised products (2-8 items each).
 * Tests peak/sale-day volume and PHP resource limits.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/manual/09-stress-test-50-orders.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

// Increase limits for stress test
set_time_limit( 600 );
ini_set( 'memory_limit', '512M' );

echo "=== Manual Test 09: Stress Test — 50 Orders ===" . PHP_EOL;

$start_time = microtime( true );
$all_products = mme_test_get_all_products();
$customers = MME_TEST_CUSTOMERS;
$target = 50;
$created = [];
$total_items = 0;

for ( $i = 1; $i <= $target; $i++ ) {
    $customer = $customers[ array_rand( $customers ) ];
    $num_products = rand( 2, 8 );
    $items = mme_test_pick_products( $all_products, $num_products, 1, 5 );
    $pieces = array_sum( array_column( $items, 'qty' ) );
    $total_items += $pieces;
    $name = 'TEST-' . $customer['first'] . ' ' . $customer['last'];

    echo "  [{$i}/{$target}] {$name} ({$num_products} products, {$pieces} pieces)..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "Stress test 09: Order {$i} of {$target}"
    ] );

    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }

    // Log progress every 10 orders
    if ( $i % 10 === 0 ) {
        $elapsed_so_far = round( microtime( true ) - $start_time, 1 );
        $mem_so_far = round( memory_get_usage( true ) / 1024 / 1024, 1 );
        echo "  --- Progress: {$i}/{$target} | {$elapsed_so_far}s | {$mem_so_far}MB ---" . PHP_EOL;
    }
}

$elapsed = round( microtime( true ) - $start_time, 2 );
$mem = round( memory_get_peak_usage( true ) / 1024 / 1024, 1 );

echo PHP_EOL;
echo "DONE: " . count( $created ) . "/{$target} orders created in {$elapsed}s" . PHP_EOL;
echo "Total items across all orders: {$total_items}" . PHP_EOL;
echo "Peak memory: {$mem} MB" . PHP_EOL;
echo "Order IDs: " . implode( ', ', $created ) . PHP_EOL;
echo PHP_EOL;
echo "Now trigger the export and check:" . PHP_EOL;
echo "  - Does it complete within 5 minutes?" . PHP_EOL;
echo "  - Any PHP memory/timeout errors in debug.log?" . PHP_EOL;
echo "  - Are the CSV files significantly larger?" . PHP_EOL;
