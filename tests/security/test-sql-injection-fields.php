#!/usr/local/bin/php.cli
<?php
/**
 * Security Test: SQL Injection in Fields
 * Creates orders with SQL injection patterns in address and order notes.
 * These should not affect the database and should appear as literal text in CSV.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/security/test-sql-injection-fields.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Security Test: SQL Injection in Fields ===" . PHP_EOL;

$sql_customers = [
    [
        'first' => "Robert'; DROP TABLE wp_posts; --", 'last' => 'Tables',
        'addr1' => "1' OR '1'='1", 'addr2' => "'; DELETE FROM wp_options WHERE 1=1; --",
        'city' => 'London', 'state' => '', 'postcode' => 'E1 1AA',
        'phone' => '07700 900055', 'email' => 'test-sql-a@example.com',
    ],
    [
        'first' => 'Normal', 'last' => "' UNION SELECT * FROM wp_users --",
        'addr1' => '1; UPDATE wp_options SET option_value=0 WHERE 1=1', 'addr2' => '',
        'city' => "Manchester' AND 1=1 --", 'state' => '', 'postcode' => 'M1 1AA',
        'phone' => '07700 900056', 'email' => 'test-sql-b@example.com',
    ],
];

$items = [ [ 'id' => 15089, 'qty' => 1 ] ];

$created = [];
foreach ( $sql_customers as $idx => $customer ) {
    $label = chr( 65 + $idx );
    echo "  Order {$label}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "'; DROP TABLE wp_mme_export_log; -- SQL injection test"
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
echo "  1. Database tables must still exist (wp_posts, wp_options, wp_mme_export_log)" . PHP_EOL;
echo "  2. SQL payloads appear as literal text in CSV output" . PHP_EOL;
echo "  3. No database errors in debug.log" . PHP_EOL;
