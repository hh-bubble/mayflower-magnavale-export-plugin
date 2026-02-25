#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 12: Bundle Exclusion Test
 * Creates orders containing bundle products that MUST be excluded from export.
 * Bundles have no Magnavale code and would break warehouse processing.
 *
 * Test scenarios:
 *   A: Bundle-only order (should produce no CSV rows)
 *   B: Bundle + normal items (only normal items should export)
 *   C: Multiple bundles + normal items
 *
 * Bundle IDs: 15141, 15049, 15047, 15045, 15043, 15041
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/manual/12-bundle-exclusion-test.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Manual Test 12: Bundle Exclusion Test ===" . PHP_EOL;

$orders = [
    [
        'customer' => MME_TEST_CUSTOMERS[10], // Rebecca Hughes
        'items' => [
            [ 'id' => 15041, 'qty' => 1 ], // Dim Sum Delight Bundle
        ],
        'note' => 'Test 12A: Bundle-ONLY order. This order should produce NO CSV rows.',
        'label' => 'A (bundle only)',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[12], // Lucy Anderson
        'items' => [
            [ 'id' => 15047, 'qty' => 1 ], // Family Feast Bundle (EXCLUDE)
            [ 'id' => 15089, 'qty' => 2 ], // Siu Mai Dumplings (EXPORT)
            [ 'id' => 15077, 'qty' => 1 ], // Hoi Sin Sauce (EXPORT)
        ],
        'note' => 'Test 12B: Bundle + normal items. Only 12SMD and HS12400 should appear in CSV.',
        'label' => 'B (bundle + normal)',
    ],
    [
        'customer' => MME_TEST_CUSTOMERS[14], // Sophie Brown
        'items' => [
            [ 'id' => 15141, 'qty' => 1 ], // Mayflower Mixes Bundle (EXCLUDE)
            [ 'id' => 15049, 'qty' => 1 ], // Party Platter Bundle (EXCLUDE)
            [ 'id' => 15115, 'qty' => 1 ], // Chicken Curry (EXPORT)
            [ 'id' => 15097, 'qty' => 2 ], // Beef & Broccoli Noodles (EXPORT)
            [ 'id' => 15061, 'qty' => 1 ], // Boiled Rice (EXPORT)
        ],
        'note' => 'Test 12C: 2 bundles + 3 normal products. Only CC12227, BBNT12400, BR16200 should export.',
        'label' => 'C (2 bundles + normal)',
    ],
];

$created = [];
foreach ( $orders as $spec ) {
    $name = 'TEST-' . $spec['customer']['first'] . ' ' . $spec['customer']['last'];
    echo "  Order {$spec['label']}: {$name}..." . PHP_EOL;

    $order_id = mme_test_create_order( $spec['customer'], $spec['items'], [ 'note' => $spec['note'] ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "SUCCESS: " . count( $created ) . "/3 orders created. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo PHP_EOL . "CRITICAL CHECKS:" . PHP_EOL;
echo "  1. Bundle SKUs must NOT appear in order CSV or packing CSV" . PHP_EOL;
echo "  2. Order A: Should produce zero CSV rows (bundle only)" . PHP_EOL;
echo "  3. Order B: Only 12SMD and HS12400 in CSV" . PHP_EOL;
echo "  4. Order C: Only CC12227, BBNT12400, BR16200 in CSV" . PHP_EOL;
echo "  5. Bundle IDs to watch for: 15141, 15049, 15047, 15045, 15043, 15041" . PHP_EOL;
