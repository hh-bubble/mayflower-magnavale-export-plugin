#!/usr/local/bin/php.cli
<?php
/**
 * Automated Daily Test Order Creator — Magnavale Export Testing
 *
 * Creates 10-60 realistic test orders daily for the 2-week soft rollout.
 * Each day's batch is varied and covers most/all products in the catalog.
 * Orders are created with 'processing' status and '_magnavale_export_status'
 * = 'pending' so they get picked up by the 16:13 UK export cron.
 *
 * HOSTING PANEL SETUP (ICDSoft/SureServer):
 * ==========================================
 * Cron: Hour 10, Every 60 minutes, Mon-Fri
 * This fires at ~10:30 server time (UTC-5) = ~15:30 UK time,
 * approximately 43 minutes before the 16:13 export.
 *
 * REMOVE THIS CRON BEFORE GO-LIVE.
 *
 * @package MayflowerMagnavaleExport\Testing
 */

// ============================================================================
// CLI-ONLY GUARD
// ============================================================================

if ( php_sapi_name() !== 'cli' ) {
    http_response_code( 403 );
    echo 'This script can only be run from the command line.';
    exit( 1 );
}

// ============================================================================
// BOOTSTRAP WORDPRESS
// ============================================================================
// From tests/ → plugin dir → plugins → wp-content → WordPress root

$wp_load = dirname( __FILE__ ) . '/../../../../../../wp-load.php';

// Try alternate paths if the standard one doesn't work
if ( ! file_exists( $wp_load ) ) {
    // Try: tests/ is inside the plugin dir
    $wp_load = dirname( __FILE__ ) . '/../../../wp-load.php';
}
if ( ! file_exists( $wp_load ) ) {
    // Try relative from plugin root (plugin → plugins → wp-content → WP root)
    $wp_load = dirname( __FILE__, 2 ) . '/../../../wp-load.php';
}
if ( ! file_exists( $wp_load ) ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] FATAL: wp-load.php not found.' . PHP_EOL;
    echo 'Tried paths relative to: ' . dirname( __FILE__ ) . PHP_EOL;
    exit( 1 );
}

define( 'DOING_CRON', true );
require_once $wp_load;

// Load the shared test helpers
require_once __DIR__ . '/lib/test-helpers.php';

// ============================================================================
// CONFIGURATION
// ============================================================================

// Min/max orders per daily batch
$min_orders = 10;
$max_orders = 60;

// Use today's date as a seed component for reproducible-but-varied batches
$today_seed = crc32( date( 'Y-m-d' ) );
mt_srand( $today_seed );

// Log file
$log_file = __DIR__ . '/logs/daily-orders.log';

// ============================================================================
// DETERMINE ORDER COUNT FOR TODAY
// ============================================================================
// Vary the count each day. Use the day seed to get a deterministic but varied number.
// Distribution: mostly 15-35 (realistic days), occasionally 10-15 or 35-60

$day_of_cycle = ( (int) date( 'N' ) + (int) date( 'W' ) ) % 10; // 0-9 cycle
$order_count  = $min_orders + mt_rand( 0, $max_orders - $min_orders );

// Weight towards realistic counts (15-35) most days
if ( $day_of_cycle < 6 ) {
    $order_count = mt_rand( 15, 35 ); // Normal day
} elseif ( $day_of_cycle < 8 ) {
    $order_count = mt_rand( 35, 50 ); // Busy day
} elseif ( $day_of_cycle === 8 ) {
    $order_count = mt_rand( 50, 60 ); // Peak day (stress test)
} else {
    $order_count = mt_rand( 10, 15 ); // Quiet day
}

echo '[' . date( 'Y-m-d H:i:s' ) . "] Starting test order creation: {$order_count} orders planned." . PHP_EOL;
mme_test_log( "=== Day batch start: {$order_count} orders planned ===", $log_file );

// ============================================================================
// BUILD THE DAY'S ORDER BATCH
// ============================================================================
// Strategy:
// 1. First pass: ensure every product appears in at least one order
// 2. Second pass: fill remaining orders with random varied mixes
// 3. Sprinkle in curveballs (special chars, high quantities, bundles, etc.)

$all_products     = mme_test_get_all_products();
$frozen           = MME_TEST_FROZEN_PRODUCTS;
$sauces           = MME_TEST_SAUCE_PRODUCTS;
$sauce_pots       = MME_TEST_SAUCE_POTS;
$sauce_mixes      = MME_TEST_SAUCE_MIXES_RETAIL;
$catering         = MME_TEST_CATERING_TUBS;
$bundles          = MME_TEST_BUNDLE_PRODUCTS;
$customers        = MME_TEST_CUSTOMERS;
$created_orders   = [];
$failed_orders    = 0;

// --- PASS 1: Product Coverage Orders ---
// Distribute all products across several orders (3-5 products per order)
// This ensures every SKU gets tested every day.

$products_to_cover = $all_products;
shuffle( $products_to_cover );
$coverage_orders = [];
$chunk_size      = mt_rand( 3, 5 );
$chunks          = array_chunk( $products_to_cover, $chunk_size );

foreach ( $chunks as $chunk ) {
    $items = [];
    foreach ( $chunk as $product ) {
        $items[] = [
            'id'  => $product['id'],
            'qty' => mt_rand( 1, 4 ),
        ];
    }
    $coverage_orders[] = [
        'customer' => $customers[ array_rand( $customers ) ],
        'items'    => $items,
        'options'  => [],
    ];
}

// --- PASS 2: Fill remaining orders with varied mixes ---

$remaining = $order_count - count( $coverage_orders );
$extra_orders = [];

for ( $i = 0; $i < $remaining; $i++ ) {

    $customer = $customers[ array_rand( $customers ) ];
    $items    = [];
    $options  = [];

    // Decide order type with weighted randomness
    $type_roll = mt_rand( 1, 100 );

    if ( $type_roll <= 35 ) {
        // --- Small retail order (1-3 frozen items) ---
        $num_products = mt_rand( 1, 3 );
        $items = mme_test_pick_products( $frozen, $num_products, 1, 3 );

    } elseif ( $type_roll <= 55 ) {
        // --- Medium mixed order (3-6 items across categories) ---
        $items = array_merge(
            mme_test_pick_products( $frozen, mt_rand( 2, 4 ), 1, 3 ),
            mme_test_pick_products( $sauces, mt_rand( 1, 2 ), 1, 2 )
        );

    } elseif ( $type_roll <= 70 ) {
        // --- Large order (6-12 items, mixed everything) ---
        $items = array_merge(
            mme_test_pick_products( $frozen, mt_rand( 4, 8 ), 1, 4 ),
            mme_test_pick_products( $sauces, mt_rand( 1, 3 ), 1, 3 ),
            mme_test_pick_products( $sauce_mixes, mt_rand( 0, 2 ), 1, 2 )
        );

    } elseif ( $type_roll <= 80 ) {
        // --- Sauce-only order (ambient, no frozen) ---
        $all_sauce = array_merge( $sauces, $sauce_pots, $sauce_mixes );
        $items = mme_test_pick_products( $all_sauce, mt_rand( 2, 5 ), 1, 4 );

    } elseif ( $type_roll <= 88 ) {
        // --- Catering order (tubs + maybe some retail) ---
        $items = mme_test_pick_products( $catering, mt_rand( 1, 3 ), 1, 3 );
        if ( mt_rand( 0, 1 ) ) {
            $items = array_merge( $items, mme_test_pick_products( $sauce_mixes, 1, 1, 2 ) );
        }

    } elseif ( $type_roll <= 93 ) {
        // --- Box boundary order (exactly 18, 19, 33, 34, 51, 52, or 66 pieces) ---
        $boundary_targets = [ 18, 19, 33, 34, 51, 52, 66 ];
        $target_qty = $boundary_targets[ array_rand( $boundary_targets ) ];
        // Pick 2-4 products and distribute the target quantity
        $num_products = mt_rand( 2, 4 );
        $picked = mme_test_pick_products( $frozen, $num_products, 1, 1 );
        $total = count( $picked );
        $base_qty = intdiv( $target_qty, $total );
        $remainder_qty = $target_qty % $total;
        foreach ( $picked as $idx => &$p ) {
            $p['qty'] = $base_qty + ( $idx < $remainder_qty ? 1 : 0 );
        }
        unset( $p );
        $items = $picked;
        $options['note'] = "TEST: Box boundary target = {$target_qty} pieces";

    } elseif ( $type_roll <= 96 ) {
        // --- High quantity order (test larger box combos) ---
        $items = mme_test_pick_products( $frozen, mt_rand( 3, 5 ), 5, 12 );
        $options['note'] = 'TEST: High quantity stress order';

    } else {
        // --- Bundle inclusion order (bundle + normal items) ---
        // Bundle should be EXCLUDED from export; normal items should export
        $bundle = $bundles[ array_rand( $bundles ) ];
        $items = [
            [ 'id' => $bundle['id'], 'qty' => 1 ],
        ];
        // Add some normal products alongside the bundle
        $items = array_merge( $items, mme_test_pick_products( $frozen, mt_rand( 2, 3 ), 1, 2 ) );
        $options['note'] = "TEST: Bundle exclusion test - bundle ID {$bundle['id']} ({$bundle['name']}) must NOT appear in CSV";
    }

    if ( ! empty( $items ) ) {
        $extra_orders[] = [
            'customer' => $customer,
            'items'    => $items,
            'options'  => $options,
        ];
    }
}

// --- PASS 3: Merge and shuffle all orders ---

$all_orders = array_merge( $coverage_orders, $extra_orders );
shuffle( $all_orders );

// Trim to exact count if we have too many
$all_orders = array_slice( $all_orders, 0, $order_count );

// ============================================================================
// CREATE THE ORDERS
// ============================================================================

foreach ( $all_orders as $idx => $order_spec ) {
    $num = $idx + 1;
    $customer_name = 'TEST-' . $order_spec['customer']['first'] . ' ' . $order_spec['customer']['last'];
    $item_count = count( $order_spec['items'] );
    $total_qty = array_sum( array_column( $order_spec['items'], 'qty' ) );

    echo "  [{$num}/{$order_count}] Creating order: {$customer_name} ({$item_count} products, {$total_qty} pieces)..." . PHP_EOL;

    $order_id = mme_test_create_order(
        $order_spec['customer'],
        $order_spec['items'],
        $order_spec['options']
    );

    if ( $order_id ) {
        $created_orders[] = $order_id;
        $skus = [];
        foreach ( $order_spec['items'] as $item ) {
            $product = wc_get_product( $item['id'] );
            $sku = $product ? $product->get_sku() : "ID:{$item['id']}";
            $skus[] = "{$sku}x{$item['qty']}";
        }
        $sku_summary = implode( ', ', $skus );
        echo "    OK: Order #{$order_id} ({$sku_summary})" . PHP_EOL;
        mme_test_log( "Created order #{$order_id}: {$customer_name} - {$sku_summary}", $log_file );
    } else {
        $failed_orders++;
        echo "    FAILED" . PHP_EOL;
        mme_test_log( "FAILED to create order for {$customer_name}", $log_file );
    }
}

// ============================================================================
// SUMMARY
// ============================================================================

$success_count = count( $created_orders );
$order_ids_str = implode( ', ', $created_orders );

echo PHP_EOL;
echo '[' . date( 'Y-m-d H:i:s' ) . "] DONE: {$success_count} orders created, {$failed_orders} failed." . PHP_EOL;
echo "Order IDs: {$order_ids_str}" . PHP_EOL;

mme_test_log( "=== Day batch complete: {$success_count} created, {$failed_orders} failed ===", $log_file );
mme_test_log( "Order IDs: {$order_ids_str}", $log_file );

// Send notification email
if ( function_exists( 'mme_send_notification' ) ) {
    $status = $failed_orders > 0 ? 'Partial' : 'Success';
    mme_send_notification(
        "[Mayflower Export] Test Orders Created — {$success_count} orders",
        sprintf(
            "Test order creation completed at %s.\n\nOrders created: %d\nFailed: %d\nOrder IDs: %s\n\nThese orders are now pending export and will be picked up by the next export run.",
            date( 'Y-m-d H:i:s' ),
            $success_count,
            $failed_orders,
            $order_ids_str
        )
    );
}

exit( $failed_orders > 0 ? 1 : 0 );
