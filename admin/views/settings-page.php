<?php
/**
 * Settings Page View
 *
 * Renders the main settings form with SFTP, account, and schedule configuration.
 * Uses WordPress Settings API — fields are registered in class-admin-page.php.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="mme-settings-wrap" style="margin-top: 20px;">

    <form method="post" action="options.php">
        <?php
        // Output hidden fields (nonce, action, option_page)
        settings_fields( 'mme_settings' );

        // Render all registered sections and fields
        do_settings_sections( 'mayflower-magnavale' );

        // Save button
        submit_button( 'Save Settings' );
        ?>
    </form>

    <hr>

    <!-- SFTP Connection Test Button -->
    <h2>Connection Test</h2>
    <p>Test the SFTP connection with the saved credentials.</p>
    <button type="button" id="mme-test-sftp" class="button button-secondary">
        Test SFTP Connection
    </button>
    <span id="mme-test-result" style="margin-left: 10px;"></span>

    <hr>

    <!-- Info Panel: Server cron schedule -->
    <h2>Schedule Info</h2>
    <?php
    $cutoff_time = get_option( 'mme_cutoff_time', '16:00' );
    ?>
    <p><strong>Export schedule:</strong> Daily at 16:01 UK time (via server cron job)</p>
    <p><strong>Order cut-off:</strong> <?php echo esc_html( $cutoff_time ); ?> — orders placed after this time go into the next day's batch</p>
    <?php
    // Show pending order count
    $collector = new MME_Order_Collector();
    $pending   = $collector->get_pending_count();
    echo '<p><strong>Pending orders:</strong> ' . intval( $pending ) . '</p>';
    ?>
    <p class="description">If the server cron job fails, use the <strong>Manual Export</strong> tab as a backup.</p>

</div>
