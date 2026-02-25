<?php
/**
 * Shared Test Helpers — Product Catalog, Customer Pool, Order Creation
 *
 * Included by the daily cron script and all manual/security test scripts.
 * Contains the full product catalog, customer pool, and helper functions
 * for creating WooCommerce test orders.
 *
 * @package MayflowerMagnavaleExport\Testing
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo 'WordPress not loaded. This file must be included after wp-load.php.' . PHP_EOL;
    exit( 1 );
}

// ============================================================================
// PRODUCT CATALOG — All Magnavale products with WooCommerce IDs and SKUs
// ============================================================================

/**
 * Frozen products — require ice packs in packaging.
 * Format: [ 'id' => WC product ID, 'sku' => Magnavale code, 'name' => Product name ]
 */
define( 'MME_TEST_FROZEN_PRODUCTS', [
    [ 'id' => 15089, 'sku' => '12SMD',      'name' => 'Siu Mai Dumplings' ],
    [ 'id' => 15087, 'sku' => '12PD',       'name' => 'Prawn Dumplings' ],
    [ 'id' => 15085, 'sku' => '12CHARSIU',  'name' => 'Char Siu BBQ Pork Buns' ],
    [ 'id' => 15111, 'sku' => '12DSR',      'name' => 'Duck Spring Rolls' ],
    [ 'id' => 15079, 'sku' => 'EFR12227',   'name' => 'Vegetable Spring Rolls' ],
    [ 'id' => 15109, 'sku' => '12PW',       'name' => 'Prawn Wonton' ],
    [ 'id' => 15115, 'sku' => 'CC12227',    'name' => 'Chinese Style Chicken Curry' ],
    [ 'id' => 15063, 'sku' => 'CKP16300',   'name' => 'Kung Po Chicken' ],
    [ 'id' => 15067, 'sku' => 'CSB12300',   'name' => 'Cantonese Satay Beef' ],
    [ 'id' => 15083, 'sku' => 'SSCB12255',  'name' => 'Sweet & Sour Chicken in Batter' ],
    [ 'id' => 15103, 'sku' => 'CNC12330',   'name' => "Curry 'n' Chips" ],
    [ 'id' => 15099, 'sku' => 'GNC12330',   'name' => "Gravy 'n' Chips" ],
    [ 'id' => 15107, 'sku' => 'CBBQR12350', 'name' => 'Chinese BBQ Ribs' ],
    [ 'id' => 15105, 'sku' => 'SNPR12280',  'name' => "Salt 'n' Pepper Ribs" ],
    [ 'id' => 15097, 'sku' => 'BBNT12400',  'name' => 'Beef & Broccoli Noodles' ],
    [ 'id' => 15095, 'sku' => 'CMNT12400',  'name' => 'Chicken & Mushroom Noodles' ],
    [ 'id' => 15093, 'sku' => 'SCSNT12400', 'name' => 'Spicy Chicken Sriracha Noodles' ],
    [ 'id' => 15081, 'sku' => 'SPCNT12400', 'name' => 'Salt & Pepper Chicken Noodles' ],
    [ 'id' => 15061, 'sku' => 'BR16200',    'name' => 'Boiled Rice' ],
    [ 'id' => 15113, 'sku' => 'CBBN12400',  'name' => 'Beef in Chilli Black Bean Noodles' ],
] );

/**
 * Ambient sauces (400ml retail bottles) — may not need ice packs.
 */
define( 'MME_TEST_SAUCE_PRODUCTS', [
    [ 'id' => 15077, 'sku' => 'HS12400',   'name' => 'Hoi Sin Sauce' ],
    [ 'id' => 15075, 'sku' => 'SCS12400',  'name' => 'Sweet Chilli Sauce' ],
    [ 'id' => 15073, 'sku' => 'CBBS12400', 'name' => 'Chilli Black Bean Sauce' ],
    [ 'id' => 15071, 'sku' => 'CSSS12400', 'name' => 'Cantonese Sweet & Sour Sauce' ],
    [ 'id' => 15069, 'sku' => 'CSTS12400', 'name' => 'Cantonese Satay Stir Fry Sauce' ],
    [ 'id' => 15059, 'sku' => 'SFS12400',  'name' => 'Stir Fry Sauce' ],
    [ 'id' => 15057, 'sku' => 'SZS12400',  'name' => 'Szechuan Sauce' ],
] );

/**
 * Sauce bottles/pots (larger format).
 */
define( 'MME_TEST_SAUCE_POTS', [
    [ 'id' => 15101, 'sku' => 'CS30227', 'name' => 'Chinese Style Curry Sauce' ],
    [ 'id' => 15091, 'sku' => 'SS30227', 'name' => 'Sweet & Sour Sauce' ],
] );

/**
 * Sauce mixes — retail 255g packets.
 */
define( 'MME_TEST_SAUCE_MIXES_RETAIL', [
    [ 'id' => 15129, 'sku' => 'CSM12255A',  'name' => 'Curry Sauce Mix Original' ],
    [ 'id' => 15127, 'sku' => 'CSMH12255A', 'name' => 'Curry Sauce Mix Extra Hot' ],
    [ 'id' => 15125, 'sku' => 'SSGM12255A', 'name' => 'Southern Style Gravy Mix' ],
] );

/**
 * Sauce mixes — catering 4.54kg tubs.
 */
define( 'MME_TEST_CATERING_TUBS', [
    [ 'id' => 15055, 'sku' => 'CSMA1454',  'name' => 'Curry Sauce Mix (Catering 4.54kg)' ],
    [ 'id' => 15053, 'sku' => 'CSMAH1454', 'name' => 'Curry Sauce Mix Extra Hot (Catering 4.54kg)' ],
    [ 'id' => 15051, 'sku' => 'SSGM1454',  'name' => 'Southern Style Gravy Mix (Catering 4.54kg)' ],
] );

/**
 * Bundle products — MUST be excluded from export (no Magnavale code).
 */
define( 'MME_TEST_BUNDLE_PRODUCTS', [
    [ 'id' => 15141, 'name' => 'Mayflower Mixes Bundle' ],
    [ 'id' => 15049, 'name' => 'Party Platter Bundle' ],
    [ 'id' => 15047, 'name' => 'Family Feast Bundle' ],
    [ 'id' => 15045, 'name' => 'Sauce Selection Bundle' ],
    [ 'id' => 15043, 'name' => 'Freezer Fillers Bundle' ],
    [ 'id' => 15041, 'name' => 'Dim Sum Delight Bundle' ],
] );

/**
 * Get ALL exportable products (everything except bundles) as a flat array.
 */
function mme_test_get_all_products() {
    return array_merge(
        MME_TEST_FROZEN_PRODUCTS,
        MME_TEST_SAUCE_PRODUCTS,
        MME_TEST_SAUCE_POTS,
        MME_TEST_SAUCE_MIXES_RETAIL,
        MME_TEST_CATERING_TUBS
    );
}

// ============================================================================
// CUSTOMER POOL — 20 realistic UK test customers
// ============================================================================
// All first names will be prefixed with "TEST-" when creating orders.
// Includes varied addresses, special characters, Welsh/Scottish names, etc.

define( 'MME_TEST_CUSTOMERS', [
    [ 'first' => 'John',    'last' => 'Smith',         'addr1' => '14 Oak Lane',                     'addr2' => '',                          'city' => 'Manchester',     'state' => 'Greater Manchester', 'postcode' => 'M1 2AB',    'phone' => '07700 900001', 'email' => 'test-john.smith@example.com' ],
    [ 'first' => 'Sarah',   'last' => "O'Brien",       'addr1' => 'Flat 3',                          'addr2' => '42 High Street',            'city' => 'Leeds',          'state' => 'West Yorkshire',     'postcode' => 'LS1 3BA',   'phone' => '07700 900002', 'email' => 'test-sarah.obrien@example.com' ],
    [ 'first' => 'Wei',     'last' => 'Zhang',         'addr1' => '8 Cherry Blossom Way',            'addr2' => '',                          'city' => 'Birmingham',     'state' => 'West Midlands',      'postcode' => 'B1 1AA',    'phone' => '07700 900003', 'email' => 'test-wei.zhang@example.com' ],
    [ 'first' => 'Priya',   'last' => 'Patel-Jones',   'addr1' => '221B Baker Street',               'addr2' => '',                          'city' => 'London',         'state' => '',                   'postcode' => 'NW1 6XE',   'phone' => '07700 900004', 'email' => 'test-priya.pj@example.com' ],
    [ 'first' => 'David',   'last' => 'Williams',      'addr1' => '7 Castle Road',                   'addr2' => 'Pontcanna',                 'city' => 'Cardiff',        'state' => 'South Glamorgan',    'postcode' => 'CF11 9JR',  'phone' => '07700 900005', 'email' => 'test-david.w@example.com' ],
    [ 'first' => 'Emma',    'last' => 'Taylor',        'addr1' => '33 Victoria Crescent',            'addr2' => '',                          'city' => 'Edinburgh',      'state' => 'Midlothian',         'postcode' => 'EH1 2AB',   'phone' => '07700 900006', 'email' => 'test-emma.t@example.com' ],
    [ 'first' => 'James',   'last' => 'McGregor',      'addr1' => '12 Buchanan Street',              'addr2' => 'Floor 2',                   'city' => 'Glasgow',        'state' => 'Lanarkshire',        'postcode' => 'G1 3HL',    'phone' => '07700 900007', 'email' => 'test-james.mcg@example.com' ],
    [ 'first' => 'Fatima',  'last' => 'Khan',          'addr1' => '45 Lumb Lane',                    'addr2' => '',                          'city' => 'Bradford',       'state' => 'West Yorkshire',     'postcode' => 'BD8 7QP',   'phone' => '07700 900008', 'email' => 'test-fatima.k@example.com' ],
    [ 'first' => 'Thomas',  'last' => 'Murphy',        'addr1' => '9 Waterloo Road',                 'addr2' => 'Apt 14',                    'city' => 'Liverpool',      'state' => 'Merseyside',         'postcode' => 'L3 0BP',    'phone' => '07700 900009', 'email' => 'test-thomas.m@example.com' ],
    [ 'first' => 'Chen',    'last' => 'Li',            'addr1' => '28 Gerrard Street',               'addr2' => '',                          'city' => 'London',         'state' => '',                   'postcode' => 'W1D 6JW',   'phone' => '07700 900010', 'email' => 'test-chen.li@example.com' ],
    [ 'first' => 'Rebecca', 'last' => 'Hughes',        'addr1' => '3 Marine Parade',                 'addr2' => '',                          'city' => 'Brighton',       'state' => 'East Sussex',        'postcode' => 'BN2 1TL',   'phone' => '07700 900011', 'email' => 'test-rebecca.h@example.com' ],
    [ 'first' => 'Raj',     'last' => 'Sharma',        'addr1' => '17 Queens Road',                  'addr2' => 'Unit B',                    'city' => 'Leicester',      'state' => 'Leicestershire',     'postcode' => 'LE1 6TP',   'phone' => '07700 900012', 'email' => 'test-raj.s@example.com' ],
    [ 'first' => 'Lucy',    'last' => 'Anderson',      'addr1' => '5 The Green',                     'addr2' => 'Jesmond',                   'city' => 'Newcastle',      'state' => 'Tyne and Wear',      'postcode' => 'NE2 1TT',   'phone' => '07700 900013', 'email' => 'test-lucy.a@example.com' ],
    [ 'first' => 'Mohammed','last' => 'Ali',           'addr1' => '88 Curry Mile',                   'addr2' => '',                          'city' => 'Manchester',     'state' => 'Greater Manchester', 'postcode' => 'M14 5BD',   'phone' => '07700 900014', 'email' => 'test-mohammed.a@example.com' ],
    [ 'first' => 'Sophie',  'last' => 'Brown',         'addr1' => '2 Church Lane',                   'addr2' => 'Headingley',                'city' => 'Leeds',          'state' => 'West Yorkshire',     'postcode' => 'LS6 3BE',   'phone' => '07700 900015', 'email' => 'test-sophie.b@example.com' ],
    [ 'first' => 'Daniel',  'last' => 'Evans',         'addr1' => '11 Park Avenue',                  'addr2' => '',                          'city' => 'Bristol',        'state' => '',                   'postcode' => 'BS1 5NL',   'phone' => '07700 900016', 'email' => 'test-daniel.e@example.com' ],
    [ 'first' => 'Amy',     'last' => 'Wilson',        'addr1' => '6 Harbour View',                  'addr2' => '',                          'city' => 'Southampton',    'state' => 'Hampshire',          'postcode' => 'SO14 2AQ',  'phone' => '07700 900017', 'email' => 'test-amy.w@example.com' ],
    [ 'first' => 'Liam',    'last' => 'O\'Connor',     'addr1' => '19 Botanic Avenue',               'addr2' => '',                          'city' => 'Belfast',        'state' => 'Antrim',             'postcode' => 'BT7 1JG',   'phone' => '07700 900018', 'email' => 'test-liam.oc@example.com' ],
    [ 'first' => 'Hannah',  'last' => 'Stewart',       'addr1' => '41 Rose Street',                  'addr2' => '',                          'city' => 'Aberdeen',       'state' => 'Aberdeenshire',      'postcode' => 'AB10 1UB',  'phone' => '07700 900019', 'email' => 'test-hannah.s@example.com' ],
    [ 'first' => 'Oliver',  'last' => 'Thompson',      'addr1' => '22 Mill Hill',                    'addr2' => 'Mossley',                   'city' => 'Ashton-under-Lyne', 'state' => 'Greater Manchester', 'postcode' => 'OL5 0EF', 'phone' => '07700 900020', 'email' => 'test-oliver.t@example.com' ],
] );

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Create a single WooCommerce test order.
 *
 * @param array $customer  Customer data from MME_TEST_CUSTOMERS
 * @param array $items     Array of [ 'id' => product_id, 'qty' => quantity ]
 * @param array $options   Optional overrides: 'note' => order note string
 * @return int|false       Order ID on success, false on failure
 */
function mme_test_create_order( $customer, $items, $options = [] ) {

    // Verify WooCommerce is available
    if ( ! function_exists( 'wc_create_order' ) ) {
        echo '  ERROR: WooCommerce not available.' . PHP_EOL;
        return false;
    }

    try {
        $order = wc_create_order();

        if ( is_wp_error( $order ) ) {
            echo '  ERROR: wc_create_order() failed: ' . $order->get_error_message() . PHP_EOL;
            return false;
        }

        // Add line items
        $added_items = 0;
        foreach ( $items as $item ) {
            $product = wc_get_product( $item['id'] );
            if ( ! $product ) {
                echo "  WARNING: Product ID {$item['id']} not found, skipping." . PHP_EOL;
                continue;
            }
            $order->add_product( $product, $item['qty'] );
            $added_items++;
        }

        if ( $added_items === 0 ) {
            echo '  ERROR: No valid products added. Deleting empty order.' . PHP_EOL;
            $order->delete( true );
            return false;
        }

        // Set billing address (prefixed with TEST-)
        $order->set_billing_first_name( 'TEST-' . $customer['first'] );
        $order->set_billing_last_name( $customer['last'] );
        $order->set_billing_address_1( $customer['addr1'] );
        $order->set_billing_address_2( $customer['addr2'] );
        $order->set_billing_city( $customer['city'] );
        $order->set_billing_state( $customer['state'] );
        $order->set_billing_postcode( $customer['postcode'] );
        $order->set_billing_country( 'GB' );
        $order->set_billing_phone( $customer['phone'] );
        $order->set_billing_email( $customer['email'] );

        // Set shipping address (same as billing)
        $order->set_shipping_first_name( 'TEST-' . $customer['first'] );
        $order->set_shipping_last_name( $customer['last'] );
        $order->set_shipping_address_1( $customer['addr1'] );
        $order->set_shipping_address_2( $customer['addr2'] );
        $order->set_shipping_city( $customer['city'] );
        $order->set_shipping_state( $customer['state'] );
        $order->set_shipping_postcode( $customer['postcode'] );
        $order->set_shipping_country( 'GB' );

        // Add order note if provided
        if ( ! empty( $options['note'] ) ) {
            $order->add_order_note( $options['note'] );
        }

        // Set payment method
        $order->set_payment_method( 'bacs' );
        $order->set_payment_method_title( 'Direct Bank Transfer (Test)' );

        // Calculate totals
        $order->calculate_totals();

        // Set export meta explicitly (belt-and-braces alongside the status hook)
        $order->update_meta_data( '_magnavale_export_status', 'pending' );

        // Set status to processing — this also triggers the plugin's status hook
        $order->set_status( 'processing' );
        $order->save();

        return $order->get_id();

    } catch ( \Exception $e ) {
        echo '  ERROR: Exception creating order: ' . $e->getMessage() . PHP_EOL;
        return false;
    }
}

/**
 * Pick N random products from a given product array.
 * Returns array of [ 'id' => ..., 'qty' => ... ] ready for mme_test_create_order().
 *
 * @param array $products   Product catalog array
 * @param int   $count      Number of distinct products to pick
 * @param int   $min_qty    Minimum quantity per product
 * @param int   $max_qty    Maximum quantity per product
 * @return array
 */
function mme_test_pick_products( $products, $count, $min_qty = 1, $max_qty = 4 ) {
    $count = min( $count, count( $products ) );
    $keys  = array_rand( $products, $count );
    if ( ! is_array( $keys ) ) {
        $keys = [ $keys ];
    }

    $items = [];
    foreach ( $keys as $key ) {
        $items[] = [
            'id'  => $products[ $key ]['id'],
            'qty' => rand( $min_qty, $max_qty ),
        ];
    }
    return $items;
}

/**
 * Pick a random customer from the pool.
 *
 * @return array Customer data
 */
function mme_test_pick_customer() {
    $customers = MME_TEST_CUSTOMERS;
    return $customers[ array_rand( $customers ) ];
}

/**
 * Log a message to the test log file.
 *
 * @param string $message  The message to log
 * @param string $log_file Full path to the log file
 */
function mme_test_log( $message, $log_file = null ) {
    if ( $log_file === null ) {
        $log_file = dirname( __DIR__ ) . '/logs/daily-orders.log';
    }

    $log_dir = dirname( $log_file );
    if ( ! is_dir( $log_dir ) ) {
        mkdir( $log_dir, 0755, true );
    }

    $timestamp = date( 'Y-m-d H:i:s' );
    file_put_contents( $log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND );
}

/**
 * Bootstrap WordPress from a script inside the testing/ directory.
 * Handles the standard plugin path: wp-content/plugins/mayflower-magnavale-export/testing/
 *
 * @return bool True if WordPress loaded successfully
 */
function mme_test_bootstrap_wp() {
    // From testing/lib/ → plugin dir → plugins → wp-content → WordPress root
    $wp_load = dirname( __DIR__, 2 ) . '/../../../wp-load.php';

    if ( ! file_exists( $wp_load ) ) {
        echo 'FATAL: WordPress loader not found at: ' . realpath( dirname( $wp_load ) ) . '/wp-load.php' . PHP_EOL;
        echo 'Expected path relative to plugin: ../../../../wp-load.php' . PHP_EOL;
        return false;
    }

    define( 'DOING_CRON', true );
    require_once $wp_load;
    return true;
}
