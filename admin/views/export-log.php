<?php
/**
 * Export Log View
 *
 * Displays a paginated table of all export events with status,
 * timestamps, order counts, and filenames.
 *
 * Columns: Date/Time | Status | Message | Orders | Files
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get pagination parameters
$page     = max( 1, intval( $_GET['log_page'] ?? 1 ) );
$per_page = 25;
$status   = sanitize_text_field( $_GET['log_status'] ?? '' );

// Fetch log entries
$log = MME_Export_Logger::get_log( $page, $per_page, $status );
?>

<div class="mme-log-wrap" style="margin-top: 20px;">

    <!-- Status filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select name="log_status" id="mme-log-status-filter">
                <option value="" <?php selected( $status, '' ); ?>>All Statuses</option>
                <option value="success" <?php selected( $status, 'success' ); ?>>Success</option>
                <option value="failed" <?php selected( $status, 'failed' ); ?>>Failed</option>
                <option value="no_orders" <?php selected( $status, 'no_orders' ); ?>>No Orders</option>
            </select>
            <button type="button" class="button" onclick="
                var status = document.getElementById('mme-log-status-filter').value;
                window.location.href = '?page=mayflower-magnavale&tab=log&log_status=' + status;
            ">Filter</button>
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html( $log['total'] ); ?> items</span>
            <?php if ( $log['pages'] > 1 ) : ?>
                <?php for ( $i = 1; $i <= $log['pages']; $i++ ) : ?>
                    <?php if ( $i === $page ) : ?>
                        <span class="page-numbers current"><?php echo $i; ?></span>
                    <?php else : ?>
                        <a class="page-numbers" href="?page=mayflower-magnavale&tab=log&log_page=<?php echo $i; ?>&log_status=<?php echo esc_attr( $status ); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Log table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 160px;">Date / Time</th>
                <th style="width: 80px;">Status</th>
                <th>Message</th>
                <th style="width: 60px;">Orders</th>
                <th>Files</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $log['items'] ) ) : ?>
                <tr>
                    <td colspan="5">No export log entries found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $log['items'] as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( date( 'd/m/Y H:i:s', strtotime( $entry->export_date ) ) ); ?></td>
                        <td>
                            <?php
                            $badge_class = 'mme-badge ';
                            switch ( $entry->status ) {
                                case 'success':    $badge_class .= 'mme-exported'; break;
                                case 'failed':     $badge_class .= 'mme-failed';   break;
                                case 'no_orders':  $badge_class .= 'mme-none';     break;
                                default:           $badge_class .= 'mme-pending';  break;
                            }
                            echo '<span class="' . esc_attr( $badge_class ) . '">' . esc_html( ucfirst( $entry->status ) ) . '</span>';
                            ?>
                        </td>
                        <td><?php echo esc_html( $entry->message ); ?></td>
                        <td><?php echo intval( $entry->order_count ); ?></td>
                        <td>
                            <?php
                            if ( $entry->order_file ) {
                                echo '<small>' . esc_html( $entry->order_file ) . '</small><br>';
                            }
                            if ( $entry->packing_file ) {
                                echo '<small>' . esc_html( $entry->packing_file ) . '</small>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>
