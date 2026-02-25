#!/usr/local/bin/php.cli
<?php
/**
 * Security Test: Oversized Fields
 * Creates orders with extremely long name and address strings.
 * Tests buffer handling — should not crash or corrupt CSV structure.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/security/test-oversized-fields.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Security Test: Oversized Fields ===" . PHP_EOL;

$oversized_customers = [
    [
        'first' => str_repeat( 'A', 500 ), // 500-char first name
        'last' => str_repeat( 'B', 500 ),  // 500-char last name
        'addr1' => str_repeat( 'Street ', 1500 ), // ~10,500-char address
        'addr2' => str_repeat( 'Apt ', 2500 ),     // ~10,000-char address line 2
        'city' => str_repeat( 'City', 250 ),       // 1,000-char city
        'state' => str_repeat( 'County', 100 ),    // 600-char state
        'postcode' => 'E1 1AA',
        'phone' => '07700 900065', 'email' => 'test-oversize-a@example.com',
    ],
    [
        'first' => 'Normal', 'last' => 'Name',
        'addr1' => '1 Normal Street', 'addr2' => '',
        'city' => 'London', 'state' => '',
        'postcode' => str_repeat( '12345', 100 ), // 500-char postcode
        'phone' => str_repeat( '0', 200 ),        // 200-char phone number
        'email' => 'test-oversize-b@example.com',
    ],
];

$items = [ [ 'id' => 15089, 'qty' => 1 ] ];

$created = [];
foreach ( $oversized_customers as $idx => $customer ) {
    $label = chr( 65 + $idx );
    echo "  Order {$label}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => str_repeat( 'This is a very long order note. ', 100 ) // ~3,100 chars
    ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "Created " . count( $created ) . "/2 orders. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo PHP_EOL . "CHECKS:" . PHP_EOL;
echo "  1. Export completes without crashing" . PHP_EOL;
echo "  2. CSV remains valid (correct column count per row)" . PHP_EOL;
echo "  3. Fields are either truncated or handled without corruption" . PHP_EOL;
echo "  4. No PHP memory errors in debug.log" . PHP_EOL;
