#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 03: Multiple Orders (5 varied orders)
 * Creates 5 orders from different customers with different product mixes.
 * Tests multi-order batching, CSV row separation, and packing aggregation.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/03-multiple-orders.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 03: Multiple Orders (5 varied) ===" . PHP_EOL;

$orders = [
    [
        'customer' => MME_TEST_CUSTOMERS[0],  // John Smith
        'items' => [
            [ 'id' => 15111, 'qty' => 2 ], // Duck Spring Rolls
            [ 'id' => 15089, 'qty' => 1 ], // Siu Mai Dumplings
            [ 'id' => 15085, 'qty' => 1 ], // Char Siu BBQ Pork Buns
        ],
        'note' => 'Test 03 Order A: Dim sum selection (4 pieces = 1 small box)',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[3],  // Priya Patel-Jones
        'items' => [
            [ 'id' => 15097, 'qty' => 2 ], // Beef & Broccoli Noodles
            [ 'id' => 15061, 'qty' => 2 ], // Boiled Rice
        ],
        'note' => 'Test 03 Order B: Noodles + rice (4 pieces = 1 small box)',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[5],  // Emma Taylor
        'items' => [
            [ 'id' => 15115, 'qty' => 1 ], // Chicken Curry
            [ 'id' => 15077, 'qty' => 2 ], // Hoi Sin Sauce
            [ 'id' => 15075, 'qty' => 1 ], // Sweet Chilli Sauce
        ],
        'note' => 'Test 03 Order C: Meal + sauces mix (4 pieces = 1 small box)',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[7],  // Fatima Khan
        'items' => [
            [ 'id' => 15055, 'qty' => 2 ], // Curry Sauce Mix Catering 4.54kg
        ],
        'note' => 'Test 03 Order D: Catering tubs only (2 pieces = 1 small box)',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[9],  // Chen Li
        'items' => [
            [ 'id' => 15089, 'qty' => 3 ], // Siu Mai Dumplings
            [ 'id' => 15087, 'qty' => 3 ], // Prawn Dumplings
            [ 'id' => 15109, 'qty' => 2 ], // Prawn Wonton
            [ 'id' => 15063, 'qty' => 2 ], // Kung Po Chicken
            [ 'id' => 15067, 'qty' => 2 ], // Cantonese Satay Beef
            [ 'id' => 15057, 'qty' => 1 ], // Szechuan Sauce
        ],
        'note' => 'Test 03 Order E: Large mixed order (13 pieces = 1 small box)',
    ],
];

$created = [];
foreach ( $orders as $idx => $spec ) {
    $label = chr( 65 + $idx ); // A, B, C, D, E
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

echo PHP_EOL . "SUCCESS: " . count( $created ) . "/5 orders created. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo "Verify: Packing CSV totals should equal sum of all items across all 5 orders." . PHP_EOL;
