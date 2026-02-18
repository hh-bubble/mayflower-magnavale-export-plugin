<?php
/**
 * MME_Admin_Page
 *
 * Registers the plugin settings page under WooCommerce in the WordPress admin.
 * Handles saving/loading of all plugin configuration options.
 *
 * SETTINGS SECTIONS:
 * ==================
 * 1. FTPS Configuration  — server, port, username, password (encrypted)
 * 2. Account Settings    — Magnavale account ref, courier, DPD service code
 * 3. Schedule Settings   — order cut-off time (export time is managed by server cron)
 * 4. Manual Export        — "Export Now" button + pending order count
 * 5. Export Log          — History of all exports (separate view)
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Admin_Page {

    /**
     * Constructor — hook into WordPress admin.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_mme_test_sftp', [ $this, 'ajax_test_sftp' ] );
    }

    /**
     * Add settings page under WooCommerce menu.
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            'Magnavale Export Settings',
            'Magnavale Export',
            'manage_woocommerce',
            'mayflower-magnavale',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register all settings with the WordPress Settings API.
     */
    public function register_settings() {

        // ---- FTPS Section ----
        add_settings_section( 'mme_sftp_section', 'FTPS Configuration', function() {
            echo '<p>Configure the FTPS (FTP over TLS) server connection. Credentials are stored encrypted in the database.</p>';
        }, 'mayflower-magnavale' );

        $this->add_text_field( 'mme_sftp_host', 'FTPS Host', 's460.sureserver.com', true );
        $this->add_number_field( 'mme_sftp_port', 'FTPS Port', 21 );
        $this->add_text_field( 'mme_sftp_username', 'Username', 'magnavale', true );
        $this->add_password_field( 'mme_sftp_password', 'Password' );
        $this->add_text_field( 'mme_sftp_remote_dir', 'Remote Directory', '/' );

        // ---- Account Section ----
        add_settings_section( 'mme_account_section', 'Account Settings', function() {
            echo '<p>Magnavale account reference and courier settings. These are used in every CSV row.</p>';
        }, 'mayflower-magnavale' );

        $this->add_text_field( 'mme_account_ref', 'Account Ref', 'KING01', false, 'mme_account_section' );
        $this->add_text_field( 'mme_courier', 'Courier', 'DPD', false, 'mme_account_section' );
        $this->add_text_field( 'mme_dpd_service', 'DPD Service Code', '1^12', false, 'mme_account_section' );

        // ---- Schedule Section ----
        add_settings_section( 'mme_schedule_section', 'Order Cut-off', function() {
            echo '<p>The daily export runs at 16:01 UK time via a server cron job (see cron-export.php). ';
            echo 'The cut-off time below controls which orders are included in each delivery window.</p>';
        }, 'mayflower-magnavale' );

        $this->add_time_field( 'mme_cutoff_time', 'Order Cut-off Time', '16:00', 'mme_schedule_section' );

        // ---- Alert Section ----
        add_settings_section( 'mme_alert_section', 'Alerts', function() {
            echo '<p>Email address to receive failure alerts.</p>';
        }, 'mayflower-magnavale' );

        $this->add_text_field( 'mme_alert_email', 'Alert Email', 'holly@bubbledesign.co.uk', false, 'mme_alert_section' );

        // ---- Register all settings with appropriate sanitize callbacks ----

        // Encrypted fields: host, username, password
        // These use AES-256-CBC encryption via MME_SFTP_Uploader::encrypt() (class name kept for DB compatibility).
        // The form renders these fields blank for security, so if the submitted
        // value is empty we preserve the existing encrypted value in the database.
        $encrypted_fields = [ 'mme_sftp_host', 'mme_sftp_username', 'mme_sftp_password' ];

        foreach ( $encrypted_fields as $field ) {
            register_setting( 'mme_settings', $field, [
                'sanitize_callback' => function( $new_value ) use ( $field ) {
                    $new_value = is_string( $new_value ) ? trim( $new_value ) : '';

                    // If blank, keep the existing encrypted value (don't overwrite with empty)
                    if ( $new_value === '' ) {
                        return get_option( $field, '' );
                    }

                    // Encrypt the new value before storing
                    return MME_SFTP_Uploader::encrypt( $new_value );
                },
            ] );
        }

        // Plain-text fields with standard sanitization
        $plain_fields = [
            'mme_sftp_port', 'mme_sftp_remote_dir',
            'mme_account_ref', 'mme_courier', 'mme_dpd_service',
            'mme_cutoff_time', 'mme_alert_email',
        ];

        foreach ( $plain_fields as $field ) {
            register_setting( 'mme_settings', $field, [
                'sanitize_callback' => 'sanitize_text_field',
            ] );
        }
    }

    /**
     * Render the settings page.
     * Includes the settings form, manual export button, and export log.
     */
    public function render_settings_page() {
        // Check which tab is active
        $tab = sanitize_text_field( $_GET['tab'] ?? 'settings' );

        echo '<div class="wrap">';
        echo '<h1>Mayflower → Magnavale Export</h1>';

        // Tab navigation
        echo '<nav class="nav-tab-wrapper">';
        echo '<a href="?page=mayflower-magnavale&tab=settings" class="nav-tab ' . ( $tab === 'settings' ? 'nav-tab-active' : '' ) . '">Settings</a>';
        echo '<a href="?page=mayflower-magnavale&tab=export" class="nav-tab ' . ( $tab === 'export' ? 'nav-tab-active' : '' ) . '">Manual Export</a>';
        echo '<a href="?page=mayflower-magnavale&tab=log" class="nav-tab ' . ( $tab === 'log' ? 'nav-tab-active' : '' ) . '">Export Log</a>';
        echo '</nav>';

        switch ( $tab ) {
            case 'export':
                include MME_PLUGIN_DIR . 'admin/views/manual-export.php';
                break;
            case 'log':
                include MME_PLUGIN_DIR . 'admin/views/export-log.php';
                break;
            default:
                include MME_PLUGIN_DIR . 'admin/views/settings-page.php';
                break;
        }

        echo '</div>';
    }

    /**
     * AJAX handler: test FTPS connection from settings page.
     */
    public function ajax_test_sftp() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        check_ajax_referer( 'mme_test_sftp', 'nonce' );

        $uploader = new MME_SFTP_Uploader();
        $result   = $uploader->test_connection();
        wp_send_json( $result );
    }

    // ===================================================================
    // Helper methods for registering settings fields
    // ===================================================================

    private function add_text_field( $id, $label, $default = '', $encrypted = false, $section = 'mme_sftp_section' ) {
        add_settings_field( $id, $label, function() use ( $id, $default, $encrypted ) {
            $value = $encrypted ? '' : get_option( $id, $default );
            echo '<input type="text" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
            if ( $encrypted ) {
                $stored = get_option( $id, '' );
                echo $stored ? '<span class="dashicons dashicons-yes" style="color:green;"></span> Saved (encrypted)' : '';
            }
        }, 'mayflower-magnavale', $section );
    }

    private function add_number_field( $id, $label, $default = 0 ) {
        add_settings_field( $id, $label, function() use ( $id, $default ) {
            $value = get_option( $id, $default );
            echo '<input type="number" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" class="small-text" />';
        }, 'mayflower-magnavale', 'mme_sftp_section' );
    }

    private function add_password_field( $id, $label ) {
        add_settings_field( $id, $label, function() use ( $id ) {
            $stored = get_option( $id, '' );
            echo '<input type="password" name="' . esc_attr( $id ) . '" class="regular-text" placeholder="••••••••" />';
            if ( $stored ) {
                echo ' <span class="dashicons dashicons-yes" style="color:green;"></span> Saved (encrypted)';
            }
            echo '<p class="description">Leave blank to keep existing password.</p>';
        }, 'mayflower-magnavale', 'mme_sftp_section' );
    }

    private function add_time_field( $id, $label, $default, $section ) {
        add_settings_field( $id, $label, function() use ( $id, $default ) {
            $value = get_option( $id, $default );
            echo '<input type="time" name="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '" />';
        }, 'mayflower-magnavale', $section );
    }
}

// Instantiate the admin page
new MME_Admin_Page();
