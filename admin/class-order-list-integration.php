<?php
/**
 * MME_Order_List_Integration
 *
 * Adds Magnavale export status visibility to the WooCommerce orders list:
 *
 * 1. Custom column showing export status (pending / exported / not flagged)
 * 2. Row action to re-export a single order (reset to pending)
 * 3. Bulk action to reset multiple orders for re-export
 *
 * This gives admins quick visibility into which orders have been sent
 * to Magnavale without leaving the WooCommerce orders screen.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Order_List_Integration {

    /**
     * Constructor — hook into WooCommerce orders list.
     */
    public function __construct() {
        // Add custom column to orders list
        add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_column' ] );
        add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_column' ], 10, 2 );

        // HPOS compatible: also hook into the new orders list
        add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_column' ] );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_column_hpos' ], 10, 2 );

        // Add row action for re-export
        add_filter( 'woocommerce_order_actions', [ $this, 'add_order_action' ] );
        add_action( 'woocommerce_order_action_mme_reset_export', [ $this, 'handle_reset_export' ] );
    }

    /**
     * Add "Magnavale" column to the orders list table.
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_column( $columns ) {
        // Insert our column before the "Actions" column
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            if ( $key === 'wc_actions' || $key === 'order_actions' ) {
                $new_columns['mme_export_status'] = 'Magnavale';
            }
            $new_columns[ $key ] = $label;
        }

        // If actions column wasn't found, append at end
        if ( ! isset( $new_columns['mme_export_status'] ) ) {
            $new_columns['mme_export_status'] = 'Magnavale';
        }

        return $new_columns;
    }

    /**
     * Render the export status column content (legacy orders screen).
     *
     * @param string $column  Column key
     * @param int    $post_id Order post ID
     */
    public function render_column( $column, $post_id ) {
        if ( $column !== 'mme_export_status' ) {
            return;
        }

        $order = wc_get_order( $post_id );
        if ( ! $order ) {
            return;
        }

        $this->output_status_badge( $order );
    }

    /**
     * Render the export status column content (HPOS orders screen).
     *
     * @param string   $column Column key
     * @param WC_Order $order  Order object
     */
    public function render_column_hpos( $column, $order ) {
        if ( $column !== 'mme_export_status' ) {
            return;
        }

        $this->output_status_badge( $order );
    }

    /**
     * Output the status badge HTML for an order.
     *
     * @param WC_Order $order
     */
    private function output_status_badge( $order ) {
        $status    = $order->get_meta( '_magnavale_export_status' );
        $timestamp = $order->get_meta( '_magnavale_export_timestamp' );

        switch ( $status ) {
            case 'exported':
                $date = $timestamp ? date( 'd/m/y H:i', strtotime( $timestamp ) ) : '';
                echo '<span class="mme-badge mme-exported" title="Exported: ' . esc_attr( $date ) . '">✓ Exported</span>';
                break;

            case 'pending':
                echo '<span class="mme-badge mme-pending">⏳ Pending</span>';
                break;

            case 'failed':
                echo '<span class="mme-badge mme-failed">✗ Failed</span>';
                break;

            default:
                echo '<span class="mme-badge mme-none">—</span>';
                break;
        }
    }

    /**
     * Add "Re-export to Magnavale" to the order actions dropdown.
     * This appears on individual order edit screens.
     *
     * @param array $actions Existing actions
     * @return array Modified actions
     */
    public function add_order_action( $actions ) {
        $actions['mme_reset_export'] = 'Re-export to Magnavale (reset to pending)';
        return $actions;
    }

    /**
     * Handle the re-export action — reset order status to pending.
     *
     * @param WC_Order $order
     */
    public function handle_reset_export( $order ) {
        $collector = new MME_Order_Collector();
        $collector->reset_order( $order->get_id() );

        // Add an order note for traceability
        $order->add_order_note(
            'Magnavale export status reset to "pending" by ' . wp_get_current_user()->display_name . '. Order will be included in the next export batch.',
            false // Not a customer note
        );
    }
}

// Instantiate
new MME_Order_List_Integration();
