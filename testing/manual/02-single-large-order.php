#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 02: Single Large Order
 * Creates 1 order with 10+ items across all categories.
 * Tests large box combinations and mixed product handling.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/manual/02-single-large-order.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 02: Single Large Order ===" . PHP_EOL;

$customer = MME_TEST_CUSTOMERS[2]; // Wei Zhang
$items = [
    // Frozen dim sum (6 items)
    [ 'id' => 15089, 'qty' => 2 ], // Siu Mai Dumplings
    [ 'id' => 15087, 'qty' => 2 ], // Prawn Dumplings
    [ 'id' => 15085, 'qty' => 1 ], // Char Siu BBQ Pork Buns
    [ 'id' => 15111, 'qty' => 2 ], // Duck Spring Rolls
    [ 'id' => 15109, 'qty' => 1 ], // Prawn Wonton
    [ 'id' => 15079, 'qty' => 1 ], // Vegetable Spring Rolls
    // Frozen meals (4 items)
    [ 'id' => 15115, 'qty' => 1 ], // Chinese Style Chicken Curry
    [ 'id' => 15063, 'qty' => 1 ], // Kung Po Chicken
    [ 'id' => 15097, 'qty' => 1 ], // Beef & Broccoli Noodles
    [ 'id' => 15081, 'qty' => 1 ], // Salt & Pepper Chicken Noodles
    // Sauces (3 items)
    [ 'id' => 15077, 'qty' => 2 ], // Hoi Sin Sauce
    [ 'id' => 15075, 'qty' => 1 ], // Sweet Chilli Sauce
    [ 'id' => 15073, 'qty' => 1 ], // Chilli Black Bean Sauce
    // Sauce mix (1 item)
    [ 'id' => 15129, 'qty' => 2 ], // Curry Sauce Mix Original
];

// Total: 20 pieces = 1 large box (19-33 range)
$order_id = mme_test_create_order( $customer, $items, [ 'note' => 'Manual test 02: Single large order - 14 products, 20 pieces' ] );

if ( $order_id ) {
    echo "SUCCESS: Order #{$order_id} created (14 products, 20 pieces = 1 large box)" . PHP_EOL;
} else {
    echo "FAILED: Could not create order." . PHP_EOL;
    exit( 1 );
}
