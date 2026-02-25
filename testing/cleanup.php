#!/usr/local/bin/php.cli
<?php
/**
 * Test Data Cleanup
 * Deletes all WooCommerce orders where the billing first name starts with "TEST-".
 * Also cleans up test log files and archived CSVs from test runs.
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/cleanup.php
 *
 * Options:
 *   --dry-run    Show what would be deleted without actually deleting
 *   --orders     Only delete test orders (skip file cleanup)
 *   --files      Only delete test files (skip order cleanup)
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

$wp_load = dirname( __FILE__, 2 ) . '/../../../wp-load.php';
if ( ! file_exists( $wp_load ) ) { echo "FATAL: wp-load.php not found.\n"; exit( 1 ); }
define( 'DOING_CRON', true );
require_once $wp_load;

$dry_run    = in_array( '--dry-run', $argv );
$orders_only = in_array( '--orders', $argv );
$files_only  = in_array( '--files', $argv );
$do_orders   = ! $files_only;
$do_files    = ! $orders_only;

echo "=== Mayflower Test Data Cleanup ===" . PHP_EOL;
if ( $dry_run ) {
    echo "(DRY RUN — nothing will be deleted)" . PHP_EOL;
}
echo PHP_EOL;

// ============================================================================
// CLEAN UP TEST ORDERS
// ============================================================================

if ( $do_orders ) {
    echo "--- Finding test orders (billing name starts with TEST-) ---" . PHP_EOL;

    // Get all orders (any status) — we need to check billing name
    $all_orders = wc_get_orders( [
        'limit'  => -1,
        'return' => 'ids',
    ] );

    $test_order_ids = [];
    foreach ( $all_orders as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) continue;

        $first_name = $order->get_billing_first_name();
        if ( strpos( $first_name, 'TEST-' ) === 0 ) {
            $test_order_ids[] = $order_id;
        }
    }

    echo "Found " . count( $test_order_ids ) . " test orders." . PHP_EOL;

    if ( ! empty( $test_order_ids ) ) {
        foreach ( $test_order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            $name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $status = $order->get_status();

            if ( $dry_run ) {
                echo "  Would delete: Order #{$order_id} ({$name}, status: {$status})" . PHP_EOL;
            } else {
                $order->delete( true ); // true = force delete (bypass trash)
                echo "  Deleted: Order #{$order_id} ({$name})" . PHP_EOL;
            }
        }

        if ( ! $dry_run ) {
            echo "Deleted " . count( $test_order_ids ) . " test orders." . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

// ============================================================================
// CLEAN UP TEST FILES
// ============================================================================

if ( $do_files ) {
    echo "--- Cleaning up test files ---" . PHP_EOL;

    // Clean test logs
    $log_dir = __DIR__ . '/logs';
    if ( is_dir( $log_dir ) ) {
        $log_files = glob( $log_dir . '/*.log' );
        foreach ( $log_files as $file ) {
            if ( $dry_run ) {
                echo "  Would delete: {$file}" . PHP_EOL;
            } else {
                unlink( $file );
                echo "  Deleted: " . basename( $file ) . PHP_EOL;
            }
        }
    }

    // Clean test results
    $results_dir = __DIR__ . '/results';
    if ( is_dir( $results_dir ) ) {
        $result_files = glob( $results_dir . '/*' );
        foreach ( $result_files as $file ) {
            if ( is_file( $file ) ) {
                if ( $dry_run ) {
                    echo "  Would delete: {$file}" . PHP_EOL;
                } else {
                    unlink( $file );
                    echo "  Deleted: " . basename( $file ) . PHP_EOL;
                }
            }
        }
    }

    // Clean daily marker files
    $marker_pattern = sys_get_temp_dir() . '/mme-export-*.done';
    $markers = glob( $marker_pattern );
    foreach ( $markers as $file ) {
        if ( $dry_run ) {
            echo "  Would delete marker: {$file}" . PHP_EOL;
        } else {
            unlink( $file );
            echo "  Deleted marker: " . basename( $file ) . PHP_EOL;
        }
    }

    // Reset the test day counter
    if ( ! $dry_run ) {
        delete_option( 'mme_test_cycle_start' );
        echo "  Reset test cycle counter." . PHP_EOL;
    }

    echo PHP_EOL;
}

echo "=== Cleanup complete ===" . PHP_EOL;
if ( $dry_run ) {
    echo "Run without --dry-run to actually delete." . PHP_EOL;
}
