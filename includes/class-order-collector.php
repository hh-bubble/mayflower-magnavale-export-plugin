<?php
/**
 * MME_Order_Collector
 *
 * Responsible for querying WooCommerce to find all orders that are
 * flagged as 'pending' for Magnavale export.
 *
 * Uses wc_get_orders() with meta queries for HPOS compatibility.
 * Never queries wp_posts directly.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Order_Collector {

    /**
     * Get all orders that have been flagged as pending export.
     *
     * These are orders where:
     *   - _magnavale_export_status = 'pending'
     *   - Order status is 'processing' (safety check)
     *
     * @return WC_Order[] Array of WooCommerce order objects
     */
    public function get_pending_orders() {

        // Use WooCommerce's own query API — this works with both
        // the traditional wp_posts storage AND the new HPOS custom tables.
        $orders = wc_get_orders( [
            'status'     => 'processing',
            'limit'      => -1,  // Get all matching orders, no limit
            'orderby'    => 'ID',
            'order'      => 'ASC',
            'meta_query' => [
                [
                    'key'   => '_magnavale_export_status',
                    'value' => 'pending',
                ],
            ],
        ] );

        // Filter out any orders with no line items (edge case safety)
        $valid_orders = array_filter( $orders, function( $order ) {
            return count( $order->get_items() ) > 0;
        });

        return array_values( $valid_orders );
    }

    /**
     * Get the count of pending orders without loading full objects.
     * Useful for the admin dashboard display.
     *
     * @return int Number of orders pending export
     */
    public function get_pending_count() {
        $orders = wc_get_orders( [
            'status'     => 'processing',
            'limit'      => -1,
            'return'     => 'ids',  // Just get IDs for performance
            'meta_query' => [
                [
                    'key'   => '_magnavale_export_status',
                    'value' => 'pending',
                ],
            ],
        ] );

        return count( $orders );
    }

    /**
     * Get orders by their export batch timestamp.
     * Useful for reviewing what was in a specific export.
     *
     * @param string $batch_timestamp The batch identifier (Y-m-d_His format)
     * @return WC_Order[] Array of WooCommerce order objects
     */
    public function get_orders_by_batch( $batch_timestamp ) {
        return wc_get_orders( [
            'limit'      => -1,
            'orderby'    => 'ID',
            'order'      => 'ASC',
            'meta_query' => [
                [
                    'key'   => '_magnavale_export_batch',
                    'value' => $batch_timestamp,
                ],
            ],
        ] );
    }

    /**
     * Reset an order's export status back to 'pending'.
     * Used if an order needs to be re-exported (e.g. data was corrected).
     *
     * @param int $order_id The WooCommerce order ID
     * @return bool True if reset successful
     */
    public function reset_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        $order->update_meta_data( '_magnavale_export_status', 'pending' );
        $order->delete_meta_data( '_magnavale_export_timestamp' );
        $order->delete_meta_data( '_magnavale_export_batch' );
        $order->save();

        return true;
    }
}
