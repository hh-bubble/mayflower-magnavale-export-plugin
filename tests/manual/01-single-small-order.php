#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 01: Single Small Order
 * Creates 1 order with 1-2 frozen items. Simplest possible export test.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/01-single-small-order.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 01: Single Small Order ===" . PHP_EOL;

$customer = MME_TEST_CUSTOMERS[0]; // John Smith
$items = [
    [ 'id' => 15089, 'qty' => 2 ], // Siu Mai Dumplings x2
    [ 'id' => 15115, 'qty' => 1 ], // Chinese Style Chicken Curry x1
];

$order_id = mme_test_create_order( $customer, $items, [ 'note' => 'Manual test 01: Single small order' ] );

if ( $order_id ) {
    echo "SUCCESS: Order #{$order_id} created (3 pieces = 1 small box)" . PHP_EOL;
} else {
    echo "FAILED: Could not create order." . PHP_EOL;
    exit( 1 );
}
