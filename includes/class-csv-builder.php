<?php
/**
 * MME_CSV_Builder
 *
 * Builds the Order CSV file — one row per product line item per order.
 * No header row. 19 columns. Uses Magnavale's exact format.
 *
 * COLUMN LAYOUT (A–S):
 * ====================
 * A  = Account Ref          → Hardcoded: KING01
 * B  = Courier              → Hardcoded: DPD
 * C  = Order Ref            → WooCommerce order ID
 * D  = Customer ID          → WP user ID (0 = guest checkout)
 * E  = Blank                → Empty
 * F  = Customer Name        → Shipping first + last name
 * G  = Address Line 1       → Shipping address 1
 * H  = Address Line 2       → Shipping address 2 (can be empty)
 * I  = Town/City            → Shipping city
 * J  = County               → Shipping state/county
 * K  = Postcode             → Shipping postcode
 * L  = Delivery Date        → DD/MM/YYYY from delivery date calculator
 * M  = Product Code         → Magnavale SKU (from WooCommerce SKU — must match!)
 * N  = Product Description  → Product name
 * O  = Quantity             → Line item quantity
 * P  = Telephone            → Billing phone
 * Q  = Email                → Billing email
 * R  = Labels Required      → Total labels for this order (1 per box)
 * S  = DPD Service          → 1^12 (DPD 12:00)
 *
 * REFERENCE: See King_Asian.csv example file for the expected output format.
 * Note: The example uses KINA01 as account ref (King Asian), ours is KING01 (Mayflower).
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_CSV_Builder {

    /**
     * Build the Order CSV content string.
     *
     * If an order has 4 products, this produces 4 rows — all sharing
     * the same customer/address/delivery data. Each row only differs
     * in columns M (product code), N (description), and O (quantity).
     *
     * @param WC_Order[] $orders          Array of WooCommerce order objects
     * @param array      $delivery_dates  order_id => ['delivery_date' => 'DD/MM/YYYY', ...]
     * @param array      $box_data        order_id => ['total_labels' => int, ...]
     * @return string    The complete CSV content (no header row)
     */
    public function build( array $orders, array $delivery_dates, array $box_data ) {

        $rows = [];

        foreach ( $orders as $order ) {
            $order_id = $order->get_id();

            // Get the delivery date for this order
            $delivery_date = $delivery_dates[ $order_id ]['delivery_date'] ?? '';

            // Get the label count for this order
            $labels = $box_data[ $order_id ]['total_labels'] ?? 1;

            // Build the shared customer/address data for this order
            $shared = $this->build_shared_columns( $order, $delivery_date, $labels );

            // Loop through each line item in the order
            foreach ( $order->get_items() as $item ) {
                $product = $item->get_product();
                $quantity = intval( $item->get_quantity() );

                // Skip items with 0 quantity
                if ( $quantity <= 0 ) {
                    continue;
                }

                // Get the Magnavale product code (SKU)
                // IMPORTANT: WooCommerce SKUs MUST match Magnavale codes.
                // If they don't match, we log a warning and use the WooCommerce SKU as-is.
                $sku = '';
                if ( $product ) {
                    $sku = $product->get_sku();
                }

                // If no SKU found, log warning and use a placeholder
                if ( empty( $sku ) ) {
                    // TODO: Log warning — this order has a product with no SKU
                    $sku = 'MISSING_SKU_' . $item->get_product_id();
                    error_log( sprintf(
                        '[Mayflower Export] WARNING: Order #%d has product with no SKU (product ID: %d).',
                        $order_id,
                        $item->get_product_id()
                    ) );
                }

                // Build the product-specific columns and merge with shared columns
                $row = $shared;
                $row[12] = $sku;                    // M: Product Code
                $row[13] = $this->sanitise_csv_cell( $item->get_name() ); // N: Product Description
                $row[14] = $quantity;                // O: Quantity

                $rows[] = $row;
            }
        }

        // Convert rows to CSV string
        return $this->rows_to_csv( $rows );
    }

    /**
     * Build the shared columns (A–L, P–S) that are the same for every
     * line item within the same order.
     *
     * @param WC_Order $order          The WooCommerce order
     * @param string   $delivery_date  Delivery date in DD/MM/YYYY format
     * @param int      $labels         Number of labels (1 per box)
     * @return array   Indexed array of column values (0–18 = A–S)
     */
    private function build_shared_columns( $order, $delivery_date, $labels ) {

        // Customer name — use shipping name, fall back to billing
        $first_name = $order->get_shipping_first_name() ?: $order->get_billing_first_name();
        $last_name  = $order->get_shipping_last_name()  ?: $order->get_billing_last_name();
        $customer_name = trim( $first_name . ' ' . $last_name );

        // Customer ID — WP user ID, or 0 for guest checkout
        $customer_id = $order->get_customer_id() ?: 0;

        // Address fields — use shipping address
        $address_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
        $address_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
        $city      = $order->get_shipping_city()      ?: $order->get_billing_city();
        $county    = $order->get_shipping_state()      ?: $order->get_billing_state();
        $postcode  = $order->get_shipping_postcode()   ?: $order->get_billing_postcode();

        // Phone and email — always from billing
        $phone = $order->get_billing_phone() ?: '';
        $email = $order->get_billing_email() ?: '';

        // Build indexed array matching columns A (0) through S (18)
        return [
            0  => MME_ACCOUNT_REF,          // A: Account Ref
            1  => MME_COURIER,              // B: Courier
            2  => $order->get_id(),         // C: Order Ref
            3  => $customer_id,             // D: Customer ID
            4  => '',                       // E: Blank
            5  => $customer_name,           // F: Customer Name
            6  => $address_1,               // G: Address Line 1
            7  => $address_2,               // H: Address Line 2
            8  => $city,                    // I: Town/City
            9  => $county,                  // J: County
            10 => $postcode,                // K: Postcode
            11 => $delivery_date,           // L: Delivery Date
            12 => '',                       // M: Product Code (filled per line item)
            13 => '',                       // N: Product Description (filled per line item)
            14 => '',                       // O: Quantity (filled per line item)
            15 => $phone,                   // P: Telephone
            16 => $email,                   // Q: Email
            17 => $labels,                  // R: Labels Required
            18 => MME_DPD_SERVICE,          // S: DPD Service
        ];
    }

    /**
     * Convert an array of row arrays to a CSV string.
     * No header row. Uses comma delimiter. Uses \r\n line endings
     * (Windows-style, as expected by Magnavale's system).
     *
     * @param array $rows Array of row arrays
     * @return string CSV content
     */
    /**
     * Sanitise a cell value to prevent CSV injection (Excel formula injection).
     *
     * If a cell starts with =, +, -, or @, Excel/LibreOffice may interpret it
     * as a formula. Prepending a tab character neutralises this without visually
     * affecting the data in most spreadsheet applications.
     *
     * @param string $value The raw cell value
     * @return string The sanitised cell value
     */
    private function sanitise_csv_cell( $value ) {
        $value = (string) $value;
        if ( $value !== '' && in_array( $value[0], [ '=', '+', '-', '@' ], true ) ) {
            return "\t" . $value;
        }
        return $value;
    }

    private function rows_to_csv( array $rows ) {
        $output = fopen( 'php://temp', 'r+' );

        foreach ( $rows as $row ) {
            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        // Ensure Windows-style line endings (\r\n) as per the example files
        $csv = str_replace( "\n", "\r\n", $csv );
        // Fix double \r\r\n that might result from str_replace
        $csv = str_replace( "\r\r\n", "\r\n", $csv );

        return $csv;
    }
}
