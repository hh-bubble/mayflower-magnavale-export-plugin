#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 07: Mixed Categories in One Order
 * Creates 1 order containing items from every product category:
 * dim sum, noodles, meals, sauces, sauce pots, sauce mixes, catering tubs.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/07-mixed-categories.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 07: Mixed Categories ===" . PHP_EOL;

$customer = MME_TEST_CUSTOMERS[6]; // James McGregor
$items = [
    // Dim sum
    [ 'id' => 15089, 'qty' => 1 ], // Siu Mai Dumplings
    [ 'id' => 15087, 'qty' => 1 ], // Prawn Dumplings
    // Noodles
    [ 'id' => 15097, 'qty' => 1 ], // Beef & Broccoli Noodles
    [ 'id' => 15095, 'qty' => 1 ], // Chicken & Mushroom Noodles
    // Meals
    [ 'id' => 15115, 'qty' => 1 ], // Chinese Style Chicken Curry
    [ 'id' => 15103, 'qty' => 1 ], // Curry 'n' Chips
    // Rice
    [ 'id' => 15061, 'qty' => 1 ], // Boiled Rice
    // Sauces (retail bottles)
    [ 'id' => 15077, 'qty' => 1 ], // Hoi Sin Sauce
    [ 'id' => 15075, 'qty' => 1 ], // Sweet Chilli Sauce
    // Sauce pots
    [ 'id' => 15101, 'qty' => 1 ], // Chinese Style Curry Sauce
    // Sauce mixes (retail)
    [ 'id' => 15129, 'qty' => 1 ], // Curry Sauce Mix Original
    // Catering tubs
    [ 'id' => 15055, 'qty' => 1 ], // Curry Sauce Mix Catering 4.54kg
];

// Total: 12 pieces = 1 small box
$order_id = mme_test_create_order( $customer, $items, [
    'note' => 'Test 07: Mixed categories - every product type in one order (12 pieces)'
] );

if ( $order_id ) {
    echo "SUCCESS: Order #{$order_id} created (12 products from all categories, 12 pieces = 1 small box)" . PHP_EOL;
} else {
    echo "FAILED: Could not create order." . PHP_EOL;
    exit( 1 );
}
