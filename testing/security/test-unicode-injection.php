#!/usr/local/bin/php.cli
<?php
/**
 * Security Test: Unicode Injection
 * Creates orders with emoji, RTL override, zero-width characters,
 * combining diacriticals, and non-Latin scripts.
 * Tests that CSV structure is not corrupted by Unicode edge cases.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/security/test-unicode-injection.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 3 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;
require_once dirname( __DIR__ ) . '/lib/test-helpers.php';

echo "=== Security Test: Unicode Injection ===" . PHP_EOL;

$unicode_customers = [
    [
        // Emoji and zero-width characters
        'first' => "Noodle\xF0\x9F\x8D\x9CMan",  // Noodle🍜Man (emoji in name)
        'last' => "Love\xE2\x80\x8B\xE2\x80\x8BZero",  // Zero-width spaces
        'addr1' => "1 \xF0\x9F\x8F\xA0 House Emoji Street",  // 🏠 in address
        'addr2' => '', 'city' => 'London', 'state' => '', 'postcode' => 'E1 1AA',
        'phone' => '07700 900070', 'email' => 'test-unicode-a@example.com',
    ],
    [
        // RTL override and combining diacriticals
        'first' => "Normal\xE2\x80\xAEdesrever",  // RTL override character
        'last' => "Te\xCC\x88st\xCC\xA3",  // Combining diacriticals (umlaut + dot below)
        'addr1' => "\xE2\x80\xAF1 Narrow No-Break Space Street",  // Narrow NBSP
        'addr2' => "\xE2\x80\x8E\xE2\x80\x8FMixed LTR/RTL marks",  // LTR + RTL marks
        'city' => 'Leeds', 'state' => '', 'postcode' => 'LS1 1AA',
        'phone' => '07700 900071', 'email' => 'test-unicode-b@example.com',
    ],
    [
        // Non-Latin scripts (Chinese, Arabic)
        'first' => 'Test', 'last' => 'Multilingual',
        'addr1' => "\xE5\x8C\x97\xE4\xBA\xAC\xE5\xB8\x82\xE6\x9C\x9D\xE9\x98\xB3\xE5\x8C\xBA",  // 北京市朝阳区
        'addr2' => "\xD8\xB4\xD8\xA7\xD8\xB1\xD8\xB9 \xD8\xA7\xD9\x84\xD9\x85\xD9\x84\xD9\x83",  // شارع الملك
        'city' => 'International', 'state' => '', 'postcode' => 'SW1A 1AA',
        'phone' => '07700 900072', 'email' => 'test-unicode-c@example.com',
    ],
    [
        // Null bytes and control characters
        'first' => "Name\x00Hidden", 'last' => "After\x01Null",
        'addr1' => "Street\rCarriage\nReturn", 'addr2' => "Line\x0BVertical\x0CForm",
        'city' => "City\tTab", 'state' => '', 'postcode' => 'B1 1AA',
        'phone' => '07700 900073', 'email' => 'test-unicode-d@example.com',
    ],
];

$items = [ [ 'id' => 15089, 'qty' => 1 ] ];

$created = [];
foreach ( $unicode_customers as $idx => $customer ) {
    $label = chr( 65 + $idx );
    echo "  Order {$label}..." . PHP_EOL;

    $order_id = mme_test_create_order( $customer, $items, [
        'note' => "Unicode test order {$label}"
    ] );
    if ( $order_id ) {
        $created[] = $order_id;
        echo "    OK: Order #{$order_id}" . PHP_EOL;
    } else {
        echo "    FAILED" . PHP_EOL;
    }
}

echo PHP_EOL . "Created " . count( $created ) . "/4 orders. IDs: " . implode( ', ', $created ) . PHP_EOL;
echo PHP_EOL . "CHECKS:" . PHP_EOL;
echo "  1. CSV remains valid (correct column count, no broken rows)" . PHP_EOL;
echo "  2. Null bytes and control chars are stripped or escaped" . PHP_EOL;
echo "  3. RTL override doesn't corrupt column alignment" . PHP_EOL;
echo "  4. Non-Latin scripts don't break CSV parsing" . PHP_EOL;
echo "  5. FTPS upload completes successfully with Unicode content" . PHP_EOL;
