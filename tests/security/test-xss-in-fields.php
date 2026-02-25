#!/usr/local/bin/php.cli
<?php
/**
 * Security Test: XSS in Fields
 * Creates orders with HTML/JavaScript injection in customer name and address.
 * The CSV output must not contain raw HTML that could be dangerous if
 * the data is ever displayed in a web context.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/tests/security/test-xss-in-fields.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Security Test: XSS in Fields ===" . PHP_EOL;

$xss_customers = [
    [
        'first' => '<script>alert("xss")</script>', 'last' => 'Script',
        'addr1' => '<img onerror=alert(1) src=x>', 'addr2' => '',
        'city' => 'London', 'state' => '', 'postcode' => 'E1 1AA',
        'phone' => '07700 900050', 'email' => 'test-xss-a@example.com',
    ],
    [
        'first' => '<iframe src="http://evil.com">', 'last' => 'Iframe',
        'addr1' => '<body onload=alert("xss")>', 'addr2' => '<svg onload=alert(1)>',
        'city' => '<marquee>Manchester</marquee>', 'state' => '', 'postcode' => 'M1 1AA',
        'phone' => '07700 900051', 'email' => 'test-xss-b@example.com',
    ],
    [
        'first' => 'Normal', 'last' => '"><script>alert(document.cookie)</script>',
        'addr1' => "javascript:alert('xss')", 'addr2' => '<a href="javascript:void(0)">click</a>',
        'city' => 'Leeds', 'state' => 'West Yorkshire', 'postcode' => 'LS1 1AA',
        'phone' => '07700 900052', 'email' => 'test-xss-c@example.com',
    ],
];

$items = [ [ 'id' => 15089, 'qty' => 1 ] ];

$created = [];
foreach ( $xss_customers as $idx => $customer ) {
    $label = chr( 65 + $idx );
    echo "  Order {$label}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "<script>alert('order note xss')</script>"
    ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "Created " . count( $created ) . "/3 orders. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo PHP_EOL . "CHECKS after export:" . PHP_EOL;
echo "  1. CSV must not contain executable HTML/JS" . PHP_EOL;
echo "  2. Tags should be stripped or escaped" . PHP_EOL;
echo "  3. CSV should still be valid and parseable" . PHP_EOL;
