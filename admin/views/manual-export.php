<?php
/**
 * Manual Export View
 *
 * Provides a manual "Export Now" button for triggering an export on demand.
 * Shows the current pending order count and the result of the last export.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$collector     = new MME_Order_Collector();
$pending_count = $collector->get_pending_count();
$latest_log    = MME_Export_Logger::get_latest();
?>

<div class="mme-export-wrap" style="margin-top: 20px;">

    <!-- Pending Orders Status -->
    <div class="mme-status-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; max-width: 600px;">
        <h3 style="margin-top: 0;">Pending Orders</h3>
        <p style="font-size: 36px; font-weight: bold; margin: 10px 0;">
            <?php echo intval( $pending_count ); ?>
        </p>
        <p>orders waiting to be exported to Magnavale.</p>

        <?php if ( $pending_count > 0 ) : ?>
            <button type="button" id="mme-export-now" class="button button-primary button-hero">
                Export Now
            </button>
            <p class="description">This will generate and upload CSV files for all pending orders.</p>
        <?php else : ?>
            <p><em>No orders pending. New orders will be flagged when they reach "Processing" status.</em></p>
        <?php endif; ?>

        <div id="mme-export-result" style="margin-top: 15px; display: none;">
            <div class="notice inline" id="mme-export-notice">
                <p id="mme-export-message"></p>
            </div>
        </div>
    </div>

    <!-- Last Export Info -->
    <?php if ( $latest_log ) : ?>
    <div class="mme-status-box" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; max-width: 600px;">
        <h3 style="margin-top: 0;">Last Export</h3>
        <table class="form-table">
            <tr>
                <th>Date</th>
                <td><?php echo esc_html( date( 'd/m/Y H:i:s', strtotime( $latest_log->export_date ) ) ); ?></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <?php
                    $color = $latest_log->status === 'success' ? 'green' : ( $latest_log->status === 'failed' ? 'red' : 'grey' );
                    echo '<strong style="color:' . $color . ';">' . esc_html( ucfirst( $latest_log->status ) ) . '</strong>';
                    ?>
                </td>
            </tr>
            <tr>
                <th>Orders</th>
                <td><?php echo intval( $latest_log->order_count ); ?></td>
            </tr>
            <tr>
                <th>Message</th>
                <td><?php echo esc_html( $latest_log->message ); ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

</div>

<script>
/**
 * Manual export button click handler.
 * Fires AJAX request to trigger the export and shows the result.
 */
jQuery(document).ready(function($) {
    $('#mme-export-now').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Exporting...');
        $('#mme-export-result').hide();

        $.ajax({
            url: mme_ajax.url,
            type: 'POST',
            data: {
                action: 'mme_manual_export',
                nonce: mme_ajax.nonce
            },
            success: function(response) {
                var $notice = $('#mme-export-notice');
                var $message = $('#mme-export-message');

                if (response.success) {
                    var data = response.data;
                    $notice.removeClass('notice-error').addClass('notice-success');
                    $message.text('Export complete: ' + (data.message || 'Check the export log for details.'));
                } else {
                    $notice.removeClass('notice-success').addClass('notice-error');
                    $message.text('Export failed: ' + (response.data || 'Unknown error'));
                }

                $('#mme-export-result').show();
                $btn.prop('disabled', false).text('Export Now');
            },
            error: function() {
                $('#mme-export-notice').removeClass('notice-success').addClass('notice-error');
                $('#mme-export-message').text('Request failed. Check the server logs.');
                $('#mme-export-result').show();
                $btn.prop('disabled', false).text('Export Now');
            }
        });
    });
});
</script>
