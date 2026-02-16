<?php
/**
 * Uninstall handler for Mayflower Magnavale Export.
 *
 * Runs when the plugin is DELETED (not just deactivated) from WordPress.
 * Cleans up all data created by the plugin:
 *   - Plugin options from wp_options
 *   - Custom database table (mme_export_log)
 *   - Order meta data (_magnavale_export_status, _magnavale_export_timestamp, _magnavale_export_batch)
 *
 * Local CSV archive files in archives/ are deleted along with the plugin directory
 * by WordPress itself — no need to handle those here.
 *
 * @package MayflowerMagnavaleExport
 */

// Abort if not called by WordPress uninstall process
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// =========================================================================
// 1. Delete all plugin options from wp_options
// =========================================================================
$options = [
    'mme_sftp_host',
    'mme_sftp_port',
    'mme_sftp_username',
    'mme_sftp_password',
    'mme_sftp_remote_dir',
    'mme_sftp_key_path',
    'mme_account_ref',
    'mme_courier',
    'mme_dpd_service',
    'mme_cutoff_time',
    'mme_alert_email',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// =========================================================================
// 2. Drop the export log database table
// =========================================================================
$table_name = $wpdb->prefix . 'mme_export_log';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// =========================================================================
// 3. Clean up order meta data
// =========================================================================
// Uses WooCommerce HPOS-compatible approach if available, otherwise falls
// back to direct meta table query for safety.
if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) ) {
    // HPOS-compatible: query orders with our meta keys and delete them
    $meta_keys = [
        '_magnavale_export_status',
        '_magnavale_export_timestamp',
        '_magnavale_export_batch',
    ];

    foreach ( $meta_keys as $meta_key ) {
        $order_ids = wc_get_orders( [
            'meta_key'   => $meta_key,
            'limit'      => -1,
            'return'     => 'ids',
        ] );

        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->delete_meta_data( $meta_key );
                $order->save();
            }
        }
    }
} else {
    // Fallback: direct DB cleanup if WooCommerce is already deactivated
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s)",
            '_magnavale_export_status',
            '_magnavale_export_timestamp',
            '_magnavale_export_batch'
        )
    );
}

// =========================================================================
// 4. Clean up any leftover WP-Cron events (safety net)
// =========================================================================
wp_clear_scheduled_hook( 'mme_daily_export_cron' );
