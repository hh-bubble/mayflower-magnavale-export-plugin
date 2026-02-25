<?php
/**
 * Plugin Name:       Mayflower Magnavale Export
 * Plugin URI:        https://bubbledesign.co.uk
 * Description:       Exports WooCommerce orders as CSV files in Magnavale's required format
 *                    and uploads them via FTPS for fulfillment by Magnavale/DPD.
 * Version:           1.0.0
 * Author:            Bubble Design & Marketing Ltd
 * Author URI:        https://bubbledesign.co.uk
 * Requires PHP:      7.4
 * Requires at least: 5.8
 * WC requires at least: 6.0
 * WC tested up to:   8.6
 * Text Domain:       mayflower-magnavale
 *
 * @package MayflowerMagnavaleExport
 *
 * ============================================================================
 * WHAT THIS PLUGIN DOES (HIGH LEVEL)
 * ============================================================================
 *
 * 1. Hooks into WooCommerce order status changes → flags orders as "pending export"
 * 2. A server cron job (cron-export.php) runs daily at 16:01 UK time, 1 minute
 *    after the 16:00 order cut-off. Manual export from WP admin is also available.
 * 3. Calculates delivery dates based on cut-off windows (Wed→Mon, Mon→Tue, Tue→Wed)
 * 4. Calculates box combinations, labels, and ice packs per order
 * 5. Builds two CSV files:
 *    - Order CSV:   One row per line item per order (19 columns, no header)
 *    - Packing CSV:  Aggregated product totals + packaging materials (15 columns, no header)
 * 6. Uploads both CSVs to FTPS server via PHP's native FTP extension
 * 7. Marks orders as exported, logs everything, archives files locally
 *
 * Account: KING01 | Courier: DPD | Service: 1^12 (DPD 12:00)
 * ============================================================================
 */

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================================
// PLUGIN CONSTANTS
// ============================================================================

define( 'MME_VERSION',      '1.0.0' );
define( 'MME_PLUGIN_FILE',  __FILE__ );
define( 'MME_PLUGIN_DIR',   plugin_dir_path( __FILE__ ) );
define( 'MME_PLUGIN_URL',   plugin_dir_url( __FILE__ ) );
define( 'MME_PLUGIN_BASE',  plugin_basename( __FILE__ ) );

// Default config values — can be overridden via admin settings
define( 'MME_ACCOUNT_REF',  'KING01' );
define( 'MME_COURIER',      'DPD' );
define( 'MME_DPD_SERVICE',  '1^12' );  // DPD 12:00 service code — TBC with Magnavale

// ============================================================================
// AUTOLOADER — Include all class files
// ============================================================================

/**
 * Load all plugin class files from the includes/ and admin/ directories.
 * We use manual requires rather than a PSR-4 autoloader to keep things
 * simple and WordPress-native.
 */
function mme_load_classes() {
    // Shared helper functions (must load before classes that use them)
    require_once MME_PLUGIN_DIR . 'includes/helpers.php';

    // Core business logic classes
    require_once MME_PLUGIN_DIR . 'includes/class-order-collector.php';
    require_once MME_PLUGIN_DIR . 'includes/class-delivery-date-calculator.php';
    require_once MME_PLUGIN_DIR . 'includes/class-box-calculator.php';
    require_once MME_PLUGIN_DIR . 'includes/class-csv-builder.php';
    require_once MME_PLUGIN_DIR . 'includes/class-packing-list-builder.php';
    require_once MME_PLUGIN_DIR . 'includes/class-sftp-uploader.php';
    require_once MME_PLUGIN_DIR . 'includes/class-export-logger.php';

    // Admin interface classes
    require_once MME_PLUGIN_DIR . 'admin/class-admin-page.php';
    require_once MME_PLUGIN_DIR . 'admin/class-order-list-integration.php';
}
add_action( 'plugins_loaded', 'mme_load_classes' );

// ============================================================================
// WOOCOMMERCE DEPENDENCY CHECK
// ============================================================================

/**
 * Check that WooCommerce is active before doing anything.
 * Show an admin notice if it's missing.
 */
function mme_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Mayflower Magnavale Export</strong> requires WooCommerce to be installed and active.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}
add_action( 'plugins_loaded', 'mme_check_woocommerce', 5 );

// ============================================================================
// HPOS COMPATIBILITY DECLARATION
// ============================================================================

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 * We use wc_get_orders() and $order->get_meta() throughout — never query wp_posts directly.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// ============================================================================
// ORDER STATUS HOOK — Flag orders as "pending export"
// ============================================================================

/**
 * When an order transitions to "processing", mark it as pending export.
 * This is the entry point — the order gets picked up by the next server cron
 * run (daily at 16:01 UK time) or via manual export from WP admin.
 *
 * Meta key: _magnavale_export_status
 * Values:   'pending' | 'exported' | 'failed'
 *
 * We also store _magnavale_export_timestamp when exported.
 */
add_action( 'woocommerce_order_status_changed', 'mme_flag_order_for_export', 10, 4 );

function mme_flag_order_for_export( $order_id, $old_status, $new_status, $order ) {
    // Only flag when moving to processing status
    if ( $new_status !== 'processing' ) {
        return;
    }

    // Don't re-flag orders that have already been exported
    $current_status = $order->get_meta( '_magnavale_export_status' );
    if ( $current_status === 'exported' ) {
        return;
    }

    // Flag the order as pending export
    $order->update_meta_data( '_magnavale_export_status', 'pending' );
    $order->save();
}

// ============================================================================
// PLUGIN ACTIVATION / DEACTIVATION
// ============================================================================

/**
 * Plugin activation — set up directories and database tables.
 *
 * The daily export is handled by a real server cron job (cron-export.php),
 * NOT by WordPress's pseudo-cron. See cron-export.php for crontab setup.
 */
register_activation_hook( __FILE__, 'mme_activate' );
function mme_activate() {
    // Load classes manually — activation hook fires BEFORE plugins_loaded,
    // so mme_load_classes() hasn't run yet at this point.
    require_once MME_PLUGIN_DIR . 'includes/class-export-logger.php';

    // Clean up any leftover WP-Cron events from previous versions
    wp_clear_scheduled_hook( 'mme_daily_export_cron' );

    // Create the archive directory for CSV files
    $archive_dir = MME_PLUGIN_DIR . 'archives/';
    if ( ! file_exists( $archive_dir ) ) {
        wp_mkdir_p( $archive_dir );
    }

    // Create the export log database table
    MME_Export_Logger::create_table();
}

// Clean up on deactivation
register_deactivation_hook( __FILE__, 'mme_deactivate' );
function mme_deactivate() {
    // Clean up any leftover WP-Cron events (safety net)
    wp_clear_scheduled_hook( 'mme_daily_export_cron' );
}

// ============================================================================
// MAIN EXPORT FUNCTION — The orchestrator
// ============================================================================

/**
 * Main export function — called by the server cron job (cron-export.php)
 * or manually from WP Admin → WooCommerce → Magnavale Export → Manual Export.
 *
 * This is the "conductor" that calls each class in sequence:
 *
 * 1. OrderCollector   → Get all pending orders
 * 2. DeliveryDateCalc → Calculate delivery/packing dates for each order
 * 3. BoxCalculator    → Calculate boxes, labels, ice packs per order
 * 4. CsvBuilder       → Build the order CSV (one row per line item)
 * 5. PackingListBuilder → Build the packing list CSV (aggregated totals + packaging)
 * 6. SftpUploader     → Upload both files to FTPS
 * 7. ExportLogger     → Log everything, archive files, mark orders as exported
 */
function mme_run_export() {

    // -----------------------------------------------------------------------
    // STEP 1: Collect all orders flagged as 'pending'
    // -----------------------------------------------------------------------
    $collector = new MME_Order_Collector();
    $orders    = $collector->get_pending_orders();

    // If no orders to export, log it and bail — don't upload empty files
    if ( empty( $orders ) ) {
        MME_Export_Logger::log( 'no_orders', 'No pending orders found. Export skipped.' );
        return;
    }

    // -----------------------------------------------------------------------
    // STEP 2: Calculate delivery dates for each order
    // -----------------------------------------------------------------------
    $date_calc = new MME_Delivery_Date_Calculator();

    // This returns an associative array: order_id => [ 'delivery_date' => ..., 'packing_date' => ... ]
    $delivery_dates = [];
    foreach ( $orders as $order ) {
        $delivery_dates[ $order->get_id() ] = $date_calc->calculate( $order );
    }

    // -----------------------------------------------------------------------
    // STEP 3: Calculate box combinations for each order
    // -----------------------------------------------------------------------
    $box_calc = new MME_Box_Calculator();

    // Returns: order_id => [ 'small_boxes' => int, 'large_boxes' => int, 'total_labels' => int,
    //                        'dry_ice' => int, 'regular_ice' => int, 'total_pieces' => int ]
    $box_data = [];
    foreach ( $orders as $order ) {
        $box_data[ $order->get_id() ] = $box_calc->calculate( $order );
    }

    // -----------------------------------------------------------------------
    // STEP 4: Build the Order CSV
    // -----------------------------------------------------------------------
    $csv_builder = new MME_CSV_Builder();
    $order_csv   = $csv_builder->build( $orders, $delivery_dates, $box_data );

    // -----------------------------------------------------------------------
    // STEP 5: Build the Packing List CSV
    // -----------------------------------------------------------------------
    $packing_builder = new MME_Packing_List_Builder();
    $packing_csv     = $packing_builder->build( $orders, $delivery_dates, $box_data );

    // -----------------------------------------------------------------------
    // STEP 6: Generate filenames and save locally
    // -----------------------------------------------------------------------
    $timestamp       = date( 'Y-m-d_His' );
    $order_filename  = MME_ACCOUNT_REF . '_ORDERS_' . $timestamp . '.csv';
    $packing_filename = MME_ACCOUNT_REF . '_PACKING_' . $timestamp . '.csv';

    // Sanitise filenames — allow only alphanumeric, underscore, hyphen, dot, caret
    $order_filename   = preg_replace( '/[^a-zA-Z0-9_.\-\^]/', '', $order_filename );
    $packing_filename = preg_replace( '/[^a-zA-Z0-9_.\-\^]/', '', $packing_filename );

    $archive_dir      = MME_PLUGIN_DIR . 'archives/';
    $order_filepath   = $archive_dir . $order_filename;
    $packing_filepath = $archive_dir . $packing_filename;

    // Verify paths resolve inside the archives directory (prevent traversal)
    $archive_real = realpath( $archive_dir );
    if ( $archive_real === false || strpos( realpath( dirname( $order_filepath ) ), $archive_real ) !== 0 ) {
        MME_Export_Logger::log( 'failed', 'Archive path validation failed. Export aborted.' );
        return;
    }

    // Write CSV content to local archive files with restrictive permissions (PII data)
    file_put_contents( $order_filepath, $order_csv );
    chmod( $order_filepath, 0600 );
    file_put_contents( $packing_filepath, $packing_csv );
    chmod( $packing_filepath, 0600 );

    // -----------------------------------------------------------------------
    // STEP 7: Upload both files to FTPS
    // -----------------------------------------------------------------------
    $uploader = new MME_SFTP_Uploader();
    $upload_result = $uploader->upload( [
        $order_filename   => $order_filepath,
        $packing_filename => $packing_filepath,
    ] );

    // -----------------------------------------------------------------------
    // STEP 8: Mark orders as exported (or failed) and log the result
    // -----------------------------------------------------------------------
    if ( $upload_result['success'] ) {
        // Mark each order as exported with timestamp
        foreach ( $orders as $order ) {
            $order->update_meta_data( '_magnavale_export_status', 'exported' );
            $order->update_meta_data( '_magnavale_export_timestamp', current_time( 'mysql' ) );
            $order->update_meta_data( '_magnavale_export_batch', $timestamp );
            $order->save();
        }

        // Log the successful export
        MME_Export_Logger::log( 'success', sprintf(
            'Exported %d orders. Files: %s, %s',
            count( $orders ),
            $order_filename,
            $packing_filename
        ), [
            'order_count'     => count( $orders ),
            'order_ids'       => array_map( fn( $o ) => $o->get_id(), $orders ),
            'order_file'      => $order_filename,
            'packing_file'    => $packing_filename,
        ] );

        // Send success notification
        $order_ids_list = implode( ', ', array_map( fn( $o ) => '#' . $o->get_id(), $orders ) );
        mme_send_notification(
            '[Mayflower Export] Success — ' . count( $orders ) . ' orders exported',
            sprintf(
                "The Magnavale export completed successfully at %s.\n\nOrders exported: %d\nOrder IDs: %s\n\nFiles uploaded:\n- %s\n- %s",
                current_time( 'mysql' ),
                count( $orders ),
                $order_ids_list,
                $order_filename,
                $packing_filename
            )
        );

    } else {
        // Log the failure — don't mark orders as exported so they get retried
        MME_Export_Logger::log( 'failed', sprintf(
            'FTPS upload failed: %s',
            $upload_result['error']
        ), [
            'order_count' => count( $orders ),
            'error'       => $upload_result['error'],
        ] );

        // Send failure notification
        mme_send_notification(
            '[Mayflower Export] FAILED — FTPS Upload Error',
            sprintf(
                "The Magnavale export failed at %s.\n\nError: %s\n\n%d orders are still pending and will be retried on the next run.",
                current_time( 'mysql' ),
                $upload_result['error'],
                count( $orders )
            )
        );
    }
}

// ============================================================================
// AJAX HANDLER — Manual export trigger from admin
// ============================================================================

/**
 * Allow admins to trigger an export manually via the settings page.
 * This serves as a backup if the server cron job fails for any reason.
 * Calls the same mme_run_export() function as cron-export.php.
 */
add_action( 'wp_ajax_mme_manual_export', 'mme_ajax_manual_export' );

function mme_ajax_manual_export() {
    // Security check — only admins can trigger this
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( 'Insufficient permissions.' );
    }

    // Verify the nonce
    check_ajax_referer( 'mme_manual_export', 'nonce' );

    // Run the export
    mme_run_export();

    // Return the result (the logger will have the latest entry)
    $latest_log = MME_Export_Logger::get_latest();
    wp_send_json_success( $latest_log );
}

// ============================================================================
// ADMIN ASSETS
// ============================================================================

/**
 * Enqueue admin CSS and JS on our plugin pages only.
 */
add_action( 'admin_enqueue_scripts', 'mme_admin_assets' );

function mme_admin_assets( $hook ) {
    // Only load on our plugin settings page
    if ( strpos( $hook, 'mayflower-magnavale' ) === false ) {
        return;
    }

    wp_enqueue_style(
        'mme-admin',
        MME_PLUGIN_URL . 'assets/admin.css',
        [],
        MME_VERSION
    );

    wp_enqueue_script(
        'mme-admin',
        MME_PLUGIN_URL . 'assets/admin.js',
        [ 'jquery' ],
        MME_VERSION,
        true
    );

    wp_localize_script( 'mme-admin', 'mme_ajax', [
        'url'          => admin_url( 'admin-ajax.php' ),
        'export_nonce' => wp_create_nonce( 'mme_manual_export' ),
        'sftp_nonce'   => wp_create_nonce( 'mme_test_sftp' ),
    ] );
}
