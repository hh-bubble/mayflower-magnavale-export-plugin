#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 05: Box Boundary Quantities
 * Creates orders at exact box size thresholds to test the box calculator.
 *
 * Box tiers:
 *   1-18 pieces  = 1 small box
 *   19-33 pieces = 1 large box
 *   34-51 pieces = 1 small + 1 large
 *   52-66 pieces = 2 large
 *   67+          = pattern continues
 *
 * This creates orders at: 18, 19, 33, 34, 51, 52, 66, 67 pieces.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/05-boundary-quantities.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 05: Box Boundary Quantities ===" . PHP_EOL;

$boundaries = [
    [ 'qty' => 18, 'expected' => '1 small box',           'label' => 'A' ],
    [ 'qty' => 19, 'expected' => '1 large box',           'label' => 'B' ],
    [ 'qty' => 33, 'expected' => '1 large box',           'label' => 'C' ],
    [ 'qty' => 34, 'expected' => '1 small + 1 large box', 'label' => 'D' ],
    [ 'qty' => 51, 'expected' => '1 small + 1 large box', 'label' => 'E' ],
    [ 'qty' => 52, 'expected' => '2 large boxes',         'label' => 'F' ],
    [ 'qty' => 66, 'expected' => '2 large boxes',         'label' => 'G' ],
    [ 'qty' => 67, 'expected' => '2 large + 1 small box', 'label' => 'H' ],
];

$customers = MME_TEST_CUSTOMERS;
$frozen = MME_TEST_FROZEN_PRODUCTS;
$created = [];

foreach ( $boundaries as $idx => $b ) {
    $customer = $customers[ $idx % count( $customers ) ];
    $name = 'TEST-' . $customer['first'] . ' ' . $customer['last'];

    // Distribute the target quantity across 2-3 products
    $product1 = $frozen[ $idx % count( $frozen ) ];
    $product2 = $frozen[ ( $idx + 5 ) % count( $frozen ) ];
    $qty1 = intdiv( $b['qty'], 2 );
    $qty2 = $b['qty'] - $qty1;

    $items = [
        [ 'id' => $product1['id'], 'qty' => $qty1 ],
        [ 'id' => $product2['id'], 'qty' => $qty2 ],
    ];

    echo "  Order {$b['label']}: {$b['qty']} pieces => expected {$b['expected']}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "Test 05 Order {$b['label']}: {$b['qty']} pieces - expect {$b['expected']}"
    ] );

    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id} ({$name})" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "SUCCESS: " . count( $created ) . "/8 orders created. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo "Verify: Check 'Labels Required' (col R) in order CSV matches expected box counts." . PHP_EOL;
