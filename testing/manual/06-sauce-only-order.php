#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 06: Sauce-Only Order (Ambient Products)
 * Creates orders with only ambient products (sauces, sauce mixes) — no frozen items.
 * Tests whether ice packs are still added (question: do ambient-only orders need ice?).
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/manual/06-sauce-only-order.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 06: Sauce-Only Orders (Ambient) ===" . PHP_EOL;

$orders = [
    [
        'customer' => MME_TEST_CUSTOMERS[4],  // David Williams
        'items' => [
            [ 'id' => 15077, 'qty' => 2 ], // Hoi Sin Sauce
            [ 'id' => 15075, 'qty' => 2 ], // Sweet Chilli Sauce
            [ 'id' => 15073, 'qty' => 1 ], // Chilli Black Bean Sauce
        ],
        'note' => 'Test 06 Order A: Retail sauce bottles only',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[11], // Raj Sharma
        'items' => [
            [ 'id' => 15129, 'qty' => 3 ], // Curry Sauce Mix Original
            [ 'id' => 15127, 'qty' => 2 ], // Curry Sauce Mix Extra Hot
            [ 'id' => 15125, 'qty' => 2 ], // Southern Style Gravy Mix
        ],
        'note' => 'Test 06 Order B: Retail sauce mixes only',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[15], // Daniel Evans
        'items' => [
            [ 'id' => 15101, 'qty' => 1 ], // Chinese Style Curry Sauce pot
            [ 'id' => 15091, 'qty' => 1 ], // Sweet & Sour Sauce pot
            [ 'id' => 15059, 'qty' => 2 ], // Stir Fry Sauce
            [ 'id' => 15057, 'qty' => 1 ], // Szechuan Sauce
        ],
        'note' => 'Test 06 Order C: Mixed sauce types (bottles + pots)',
    ],
];

$created = [];
foreach ( $orders as $idx => $spec ) {
    $label = chr( 65 + $idx );
    $name = 'TEST-' . $spec['customer']['first'] . ' ' . $spec['customer']['last'];
    echo "  Creating Order {$label}: {$name}..." . PHP_EOL;

    $order_id = mme_test_create_order( $spec['customer'], $spec['items'], [ 'note' => $spec['note'] ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "SUCCESS: " . count( $created ) . "/3 orders created. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo "Verify: Check if ice packs appear in packing CSV for ambient-only orders." . PHP_EOL;
echo "Question: Should ambient-only orders have ice packs or not?" . PHP_EOL;
