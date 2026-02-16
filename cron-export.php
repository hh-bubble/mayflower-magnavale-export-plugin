<?php
/**
 * Server Cron Entry Point — Magnavale Export
 *
 * This script is called directly by the server's crontab to run the daily
 * Magnavale export. It bootstraps WordPress, acquires a file lock to prevent
 * overlapping runs, and calls mme_run_export().
 *
 * CRONTAB SETUP:
 * ==============
 * Add the following to the server's crontab (crontab -e):
 *
 *   CRON_TZ=Europe/London
 *   1 16 * * * /usr/bin/flock -n /tmp/mme-export.lock /usr/bin/php /path/to/wp-content/plugins/mayflower-magnavale-export/cron-export.php >> /var/log/mme-export.log 2>&1
 *
 * This runs at 16:01 UK time every day (1 minute after the 16:00 cut-off).
 * CRON_TZ=Europe/London handles BST/GMT transitions automatically.
 * flock -n prevents overlapping runs if a previous export is still in progress.
 *
 * MANUAL BACKUP:
 * ==============
 * If this cron job fails, admins can trigger an export manually from:
 *   WP Admin → WooCommerce → Magnavale Export → Manual Export tab
 *
 * @package MayflowerMagnavaleExport
 */

// ============================================================================
// CLI-ONLY GUARD — Reject web requests
// ============================================================================

if ( php_sapi_name() !== 'cli' ) {
    http_response_code( 403 );
    echo 'This script can only be run from the command line.';
    exit( 1 );
}

// ============================================================================
// FILE LOCK — Prevent concurrent execution
// ============================================================================
// This is a second layer of protection in addition to flock in the crontab.
// Handles edge cases where the script is called directly without flock.

$lock_file = sys_get_temp_dir() . '/mme-export.lock';
$lock_fp   = fopen( $lock_file, 'w' );

if ( ! $lock_fp || ! flock( $lock_fp, LOCK_EX | LOCK_NB ) ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] SKIPPED: Another export is already running.' . PHP_EOL;
    exit( 0 ); // Exit cleanly — not an error, just a skip
}

// ============================================================================
// EXECUTION LIMITS
// ============================================================================

set_time_limit( 300 ); // 5-minute max execution time
ini_set( 'memory_limit', '256M' );

// ============================================================================
// BOOTSTRAP WORDPRESS
// ============================================================================
// Standard plugin location: wp-content/plugins/mayflower-magnavale-export/
// wp-load.php is 4 levels up: ../../../../wp-load.php

$wp_load = dirname( __FILE__ ) . '/../../../../wp-load.php';

if ( ! file_exists( $wp_load ) ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] FATAL: Cannot find wp-load.php at: ' . $wp_load . PHP_EOL;
    echo 'If WordPress is installed in a non-standard location, update the path in this file.' . PHP_EOL;
    flock( $lock_fp, LOCK_UN );
    fclose( $lock_fp );
    exit( 1 );
}

// Tell WordPress this is a cron context
define( 'DOING_CRON', true );

require_once $wp_load;

// ============================================================================
// VERIFY PLUGIN IS ACTIVE
// ============================================================================

if ( ! function_exists( 'mme_run_export' ) ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] FATAL: mme_run_export() not found. Is the plugin active?' . PHP_EOL;
    flock( $lock_fp, LOCK_UN );
    fclose( $lock_fp );
    exit( 1 );
}

// ============================================================================
// RUN THE EXPORT
// ============================================================================

echo '[' . date( 'Y-m-d H:i:s' ) . '] Starting Magnavale export...' . PHP_EOL;

try {
    mme_run_export();

    // Check the result from the export logger
    $latest = MME_Export_Logger::get_latest();

    if ( $latest && $latest->status === 'success' ) {
        echo '[' . date( 'Y-m-d H:i:s' ) . '] SUCCESS: ' . $latest->message . PHP_EOL;
        $exit_code = 0;
    } elseif ( $latest && $latest->status === 'no_orders' ) {
        echo '[' . date( 'Y-m-d H:i:s' ) . '] OK: ' . $latest->message . PHP_EOL;
        $exit_code = 0;
    } elseif ( $latest && $latest->status === 'failed' ) {
        echo '[' . date( 'Y-m-d H:i:s' ) . '] FAILED: ' . $latest->message . PHP_EOL;
        $exit_code = 1;
    } else {
        echo '[' . date( 'Y-m-d H:i:s' ) . '] WARNING: Export completed but no log entry found.' . PHP_EOL;
        $exit_code = 1;
    }
} catch ( \Exception $e ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] FATAL: Uncaught exception: ' . $e->getMessage() . PHP_EOL;
    $exit_code = 1;
} catch ( \Error $e ) {
    echo '[' . date( 'Y-m-d H:i:s' ) . '] FATAL: Uncaught error: ' . $e->getMessage() . PHP_EOL;
    $exit_code = 1;
}

// ============================================================================
// CLEANUP
// ============================================================================

flock( $lock_fp, LOCK_UN );
fclose( $lock_fp );

exit( $exit_code );
