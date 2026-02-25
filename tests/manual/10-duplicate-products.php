#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 10: Duplicate Products
 * Creates orders where the same product appears multiple times as separate line items.
 * Tests whether the CSV and packing list handle duplicate SKUs correctly.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/10-duplicate-products.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 10: Duplicate Products ===" . PHP_EOL;

$customer = MME_TEST_CUSTOMERS[8]; // Thomas Murphy

// Same product (Siu Mai Dumplings) added 3 times with different quantities
// This simulates a customer adding the same item multiple times
$items = [
    [ 'id' => 15089, 'qty' => 2 ], // Siu Mai Dumplings x2
    [ 'id' => 15089, 'qty' => 3 ], // Siu Mai Dumplings x3 (duplicate!)
    [ 'id' => 15089, 'qty' => 1 ], // Siu Mai Dumplings x1 (triplicate!)
    [ 'id' => 15077, 'qty' => 2 ], // Hoi Sin Sauce x2 (different product)
    [ 'id' => 15077, 'qty' => 1 ], // Hoi Sin Sauce x1 (duplicate!)
];

$order_id = mme_test_create_order( $customer, $items, [
    'note' => 'Test 10: Duplicate products - 12SMD appears 3 times, HS12400 appears 2 times'
] );

if ( $order_id ) {
    echo "SUCCESS: Order #{$order_id} created" . PHP_EOL;
    echo "  - 12SMD (Siu Mai Dumplings): 2 + 3 + 1 = 6 total" . PHP_EOL;
    echo "  - HS12400 (Hoi Sin Sauce): 2 + 1 = 3 total" . PHP_EOL;
    echo "  - Total pieces: 9 = 1 small box" . PHP_EOL;
    echo "Verify: Order CSV should have 5 rows (one per line item)." . PHP_EOL;
    echo "Verify: Packing CSV should aggregate to 6x 12SMD + 3x HS12400." . PHP_EOL;
} else {
    echo "FAILED: Could not create order." . PHP_EOL;
    exit( 1 );
}
