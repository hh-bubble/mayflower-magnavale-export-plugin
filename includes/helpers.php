<?php
/**
 * Shared helper functions for the Mayflower Magnavale Export plugin.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get expanded line items for an order, resolving bundle products
 * into their individual components.
 *
 * Bundles are built with ACF repeater fields:
 *   - bundle_items              → count of sub-items
 *   - bundle_items_{n}_sub_product → WooCommerce product ID of component
 *   - bundle_items_{n}_sub_qty    → quantity of that component per 1 bundle
 *
 * @param WC_Order $order The WooCommerce order
 * @return array[] Array of [ 'product' => WC_Product, 'name' => string, 'qty' => int ]
 */
function mme_get_expanded_items( $order ) {
    $items = [];

    foreach ( $order->get_items() as $item ) {
        $product  = $item->get_product();
        $quantity = intval( $item->get_quantity() );

        if ( $quantity <= 0 || ! $product ) {
            continue;
        }

        $product_id   = $product->get_id();
        $bundle_count = (int) get_post_meta( $product_id, 'bundle_items', true );

        if ( $bundle_count > 0 ) {
            // Bundle product: expand into individual components
            for ( $i = 0; $i < $bundle_count; $i++ ) {
                $child_id  = (int) get_post_meta( $product_id, "bundle_items_{$i}_sub_product", true );
                $child_qty = (int) get_post_meta( $product_id, "bundle_items_{$i}_sub_qty", true );

                if ( $child_id <= 0 || $child_qty <= 0 ) {
                    continue;
                }

                $child_product = wc_get_product( $child_id );
                if ( ! $child_product ) {
                    continue;
                }

                $items[] = [
                    'product' => $child_product,
                    'name'    => $child_product->get_name(),
                    'qty'     => $child_qty * $quantity,
                ];
            }
        } else {
            // Normal product: pass through as-is
            $items[] = [
                'product' => $product,
                'name'    => $item->get_name(),
                'qty'     => $quantity,
            ];
        }
    }

    return $items;
}

/**
 * Get the list of admin notification email addresses.
 *
 * Reads from the mme_alert_emails option (comma-separated).
 * Falls back to the legacy single mme_alert_email option.
 *
 * @return string[] Array of trimmed, valid email addresses
 */
function mme_get_alert_emails() {
    $emails_str = get_option( 'mme_alert_emails', '' );

    // Fall back to legacy single email option
    if ( empty( $emails_str ) ) {
        $emails_str = get_option( 'mme_alert_email', 'holly@bubbledesign.co.uk' );
    }

    $emails = array_map( 'trim', explode( ',', $emails_str ) );
    $emails = array_filter( $emails, 'is_email' );

    return array_values( $emails );
}

/**
 * Send a notification email to all configured admin addresses.
 *
 * @param string $subject Email subject line
 * @param string $message Email body (plain text)
 */
function mme_send_notification( $subject, $message ) {
    $emails = mme_get_alert_emails();

    if ( empty( $emails ) ) {
        return;
    }

    wp_mail( $emails, $subject, $message );
}
