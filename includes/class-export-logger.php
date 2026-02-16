<?php
/**
 * MME_Export_Logger
 *
 * Handles logging of export events to a custom database table and
 * manages the local file archive.
 *
 * THREE LAYERS OF TRACEABILITY:
 * =============================
 * 1. Database log    → Custom table with export status, timestamps, order counts
 * 2. Local archive   → CSV files saved to plugin's archives/ directory
 * 3. SFTP server     → Files available on the remote server for Magnavale
 *
 * GDPR NOTE:
 * Export logs should NOT store full personal data — only order IDs and timestamps.
 * The actual CSV files in the archive contain personal data and should be
 * cleaned up periodically (configurable retention period).
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Export_Logger {

    /**
     * Custom database table name (without prefix).
     *
     * @var string
     */
    const TABLE_NAME = 'mme_export_log';

    /**
     * Create the custom database table on plugin activation.
     * Called from register_activation_hook in the main plugin file.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            export_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            message TEXT,
            order_count INT(11) DEFAULT 0,
            order_ids TEXT,
            order_file VARCHAR(255) DEFAULT '',
            packing_file VARCHAR(255) DEFAULT '',
            meta LONGTEXT,
            PRIMARY KEY (id),
            KEY status (status),
            KEY export_date (export_date)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store the DB version for future migrations
        update_option( 'mme_db_version', '1.0.0' );
    }

    /**
     * Log an export event to the database.
     *
     * @param string $status  'success', 'failed', 'no_orders'
     * @param string $message Human-readable description
     * @param array  $data    Optional additional data (order_count, order_ids, filenames)
     * @return int|false Insert ID on success, false on failure
     */
    public static function log( $status, $message, $data = [] ) {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Build the row data
        $row = [
            'export_date'  => current_time( 'mysql' ),
            'status'       => sanitize_text_field( $status ),
            'message'      => sanitize_textarea_field( $message ),
            'order_count'  => intval( $data['order_count'] ?? 0 ),
            'order_ids'    => isset( $data['order_ids'] ) ? implode( ',', $data['order_ids'] ) : '',
            'order_file'   => sanitize_text_field( $data['order_file'] ?? '' ),
            'packing_file' => sanitize_text_field( $data['packing_file'] ?? '' ),
            'meta'         => wp_json_encode( $data ),
        ];

        $result = $wpdb->insert( $table_name, $row, [
            '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
        ] );

        if ( $result === false ) {
            error_log( '[Mayflower Export] Failed to write to export log: ' . $wpdb->last_error );
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get the most recent log entry.
     * Used after manual export to show the result in the admin UI.
     *
     * @return object|null Log entry object or null
     */
    public static function get_latest() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d", 1 )
        );
    }

    /**
     * Get paginated log entries for the admin export log view.
     *
     * @param int    $page     Page number (1-indexed)
     * @param int    $per_page Items per page
     * @param string $status   Optional status filter ('success', 'failed', 'no_orders', or '' for all)
     * @return array { 'items' => array, 'total' => int, 'pages' => int }
     */
    public static function get_log( $page = 1, $per_page = 20, $status = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $where = '';
        $params = [];

        if ( ! empty( $status ) ) {
            $where = 'WHERE status = %s';
            $params[] = $status;
        }

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table_name} {$where}";
        $total = empty( $params )
            ? $wpdb->get_var( $count_sql )
            : $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

        // Get paginated results
        $offset = ( $page - 1 ) * $per_page;
        $query  = "SELECT * FROM {$table_name} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $items = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        return [
            'items' => $items ?: [],
            'total' => intval( $total ),
            'pages' => ceil( intval( $total ) / $per_page ),
        ];
    }

    /**
     * Clean up old archive files based on retention period.
     * Should be called periodically (e.g. weekly) to respect GDPR.
     *
     * @param int $days_to_keep Number of days to retain archive files (default: 30)
     * @return int Number of files deleted
     */
    public static function cleanup_archives( $days_to_keep = 30 ) {
        $archive_dir = MME_PLUGIN_DIR . 'archives/';

        if ( ! is_dir( $archive_dir ) ) {
            return 0;
        }

        $cutoff   = time() - ( $days_to_keep * DAY_IN_SECONDS );
        $deleted  = 0;

        foreach ( glob( $archive_dir . '*.csv' ) as $file ) {
            if ( filemtime( $file ) < $cutoff ) {
                if ( unlink( $file ) ) {
                    $deleted++;
                }
            }
        }

        if ( $deleted > 0 ) {
            self::log( 'cleanup', sprintf(
                'Cleaned up %d archive files older than %d days.',
                $deleted,
                $days_to_keep
            ) );
        }

        return $deleted;
    }
}
