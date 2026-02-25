#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 04: Edge Case Names & Addresses
 * Creates 4 orders with challenging customer data: apostrophes, accents,
 * ampersands, commas, quotes, Welsh/Irish characters, hyphenated names.
 * Tests CSV field escaping and encoding.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/04-edge-case-names.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 04: Edge Case Names & Addresses ===" . PHP_EOL;

// Custom customers with tricky characters
$tricky_customers = [
    [
        'first' => "Patrick",  'last' => "O'Brien-McCarthy",
        'addr1' => 'Flat 3, "The Willows"', 'addr2' => "42 O'Connell Street",
        'city' => "Dun Laoghaire", 'state' => 'Dublin', 'postcode' => 'A96 D6W3',
        'phone' => '07700 900030', 'email' => 'test-patrick.ob@example.com',
    ],
    [
        'first' => "Sian",  'last' => "Davies",
        'addr1' => "Ty Newydd", 'addr2' => "Ffordd y Mor",
        'city' => "Aberystwyth", 'state' => 'Ceredigion', 'postcode' => 'SY23 1DE',
        'phone' => '07700 900031', 'email' => 'test-sian.d@example.com',
    ],
    [
        'first' => "Jean-Pierre",  'last' => "Smith & Sons",
        'addr1' => '14 King\'s Road', 'addr2' => 'c/o The Red Lion & Crown',
        'city' => "Newcastle-upon-Tyne", 'state' => 'Tyne & Wear', 'postcode' => 'NE1 3BN',
        'phone' => '07700 900032', 'email' => 'test-jp.smith@example.com',
    ],
    [
        'first' => "Niamh",  'last' => "O'Sullivan",
        'addr1' => "Apartment 12/B", 'addr2' => "St. Mary's Close (rear entrance)",
        'city' => "Stratford-upon-Avon", 'state' => 'Warwickshire', 'postcode' => 'CV37 6BA',
        'phone' => '07700 900033', 'email' => 'test-niamh.os@example.com',
    ],
];

$items = [
    [ 'id' => 15089, 'qty' => 1 ], // Siu Mai Dumplings
    [ 'id' => 15077, 'qty' => 1 ], // Hoi Sin Sauce
];

$created = [];
foreach ( $tricky_customers as $idx => $customer ) {
    $label = chr( 65 + $idx );
    $name = 'TEST-' . $customer['first'] . ' ' . $customer['last'];
    echo "  Creating Order {$label}: {$name}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "Test 04 Order {$label}: Edge case name/address characters"
    ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "SUCCESS: " . count( $created ) . "/4 orders created. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo "Verify: CSV fields must be properly escaped. No field bleeding across columns." . PHP_EOL;
