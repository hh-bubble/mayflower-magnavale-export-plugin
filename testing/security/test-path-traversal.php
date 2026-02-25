#!/usr/local/bin/php.cli
<?php
/**
 * Security Test: Path Traversal in Fields
 * Creates orders with path traversal patterns in address fields.
 * These should not allow access to files outside the export directory.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/security/test-path-traversal.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Security Test: Path Traversal in Fields ===" . PHP_EOL;

$traversal_customers = [
    [
        'first' => 'Normal', 'last' => 'Traversal',
        'addr1' => '../../../../etc/passwd', 'addr2' => '../../../wp-config.php',
        'city' => 'London', 'state' => '', 'postcode' => 'E1 1AA',
        'phone' => '07700 900060', 'email' => 'test-path-a@example.com',
    ],
    [
        'first' => 'Windows', 'last' => 'Paths',
        'addr1' => '..\\..\\..\\wp-config.php', 'addr2' => 'C:\\Windows\\System32\\config\\SAM',
        'city' => '/etc/shadow', 'state' => '', 'postcode' => 'M1 1AA',
        'phone' => '07700 900061', 'email' => 'test-path-b@example.com',
    ],
];

$items = [ [ 'id' => 15089, 'qty' => 1 ] ];

$created = [];
foreach ( $traversal_customers as $idx => $customer ) {
    $label = chr( 65 + $idx );
    echo "  Order {$label}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => 'Path traversal test: ../../../../etc/passwd'
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
echo "  1. Path traversal strings appear as literal text in CSV" . PHP_EOL;
echo "  2. No file system access or leakage" . PHP_EOL;
echo "  3. Archive files remain in the correct directory" . PHP_EOL;
