<?php
/**
 * MME_Packing_List_Builder
 *
 * Builds the Packing List CSV file — aggregated product totals across ALL
 * orders in the batch, plus packaging material rows appended at the end.
 * No header row. 15 columns.
 *
 * COLUMN LAYOUT (A–O):
 * ====================
 * A  = Account Ref     → Hardcoded: KING01
 * B  = Courier         → Hardcoded: DPD
 * C  = Packing Date    → "Packing DD.MM.YY"
 * D  = Packing Date    → Same value as column C
 * E  = Blank           → Empty
 * F  = Text            → Hardcoded: "Packing"
 * G  = Blank           → Empty
 * H  = Blank           → Empty
 * I  = Blank           → Empty
 * J  = Blank           → Empty
 * K  = Blank           → Empty
 * L  = Delivery Date   → DD/MM/YYYY
 * M  = Product Code    → Magnavale SKU or packaging code
 * N  = Product Desc    → Product or packaging name
 * O  = Total Quantity  → Sum across ALL orders in this batch
 *
 * HOW IT WORKS:
 * =============
 * 1. Loop through all orders in the batch
 * 2. For each line item, aggregate the quantity by product SKU
 *    (e.g. if 20 customers ordered chicken breasts, show one row with qty=20)
 * 3. After all product rows, append packaging material rows:
 *    - Boxes (large/small with inserts)
 *    - Ice packs (dry ice, regular ice)
 *    These quantities are totalled across ALL orders' box calculations.
 *
 * REFERENCE: See DEL_KING01_BULK20260212.csv for the expected output format.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Packing_List_Builder {

    /**
     * Build the Packing List CSV content string.
     *
     * @param WC_Order[] $orders          Array of WooCommerce order objects
     * @param array      $delivery_dates  order_id => ['delivery_date' => ..., 'packing_date' => ...]
     * @param array      $box_data        order_id => ['packaging' => [...], ...]
     * @return string    The complete CSV content (no header row)
     */
    public function build( array $orders, array $delivery_dates, array $box_data ) {

        // ---------------------------------------------------------------
        // Step 1: Determine packing and delivery dates
        // ---------------------------------------------------------------
        // All orders in a single batch share the same delivery/packing date
        // (they were all collected in the same cut-off window)
        // Use the first order's dates as representative
        $first_order_id  = $orders[0]->get_id();
        $packing_date    = $delivery_dates[ $first_order_id ]['packing_date']  ?? '';
        $delivery_date   = $delivery_dates[ $first_order_id ]['delivery_date'] ?? '';

        // ---------------------------------------------------------------
        // Step 2: Aggregate product quantities across all orders
        // ---------------------------------------------------------------
        // Key: product SKU → [ 'code' => SKU, 'desc' => name, 'qty' => total ]
        $product_totals = [];

        foreach ( $orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                $product  = $item->get_product();
                $quantity = intval( $item->get_quantity() );

                if ( $quantity <= 0 ) {
                    continue;
                }

                // Get the SKU (should match Magnavale product code)
                $sku = '';
                if ( $product ) {
                    $sku = $product->get_sku();
                }
                if ( empty( $sku ) ) {
                    $sku = 'MISSING_SKU_' . $item->get_product_id();
                }

                // Aggregate by SKU
                if ( isset( $product_totals[ $sku ] ) ) {
                    $product_totals[ $sku ]['qty'] += $quantity;
                } else {
                    $product_totals[ $sku ] = [
                        'code' => $sku,
                        'desc' => $item->get_name(),
                        'qty'  => $quantity,
                    ];
                }
            }
        }

        // ---------------------------------------------------------------
        // Step 3: Aggregate packaging materials across all orders
        // ---------------------------------------------------------------
        // Combine all packaging items from all orders' box calculations
        $packaging_totals = [];

        foreach ( $orders as $order ) {
            $order_id   = $order->get_id();
            $packaging  = $box_data[ $order_id ]['packaging'] ?? [];

            foreach ( $packaging as $pkg ) {
                $code = $pkg['code'];
                if ( isset( $packaging_totals[ $code ] ) ) {
                    $packaging_totals[ $code ]['qty'] += $pkg['qty'];
                } else {
                    $packaging_totals[ $code ] = [
                        'code' => $pkg['code'],
                        'desc' => $pkg['desc'],
                        'qty'  => $pkg['qty'],
                    ];
                }
            }
        }

        // ---------------------------------------------------------------
        // Step 4: Build the CSV rows
        // ---------------------------------------------------------------
        $rows = [];

        // The shared columns that are the same for every row in the packing list
        $shared = [
            0  => MME_ACCOUNT_REF,   // A: Account Ref
            1  => MME_COURIER,       // B: Courier
            2  => $packing_date,     // C: Packing Date (e.g. "Packing 13.02.26")
            3  => $packing_date,     // D: Packing Date (same as C)
            4  => '',                // E: Blank
            5  => 'Packing',         // F: Text
            6  => '',                // G: Blank
            7  => '',                // H: Blank
            8  => '',                // I: Blank
            9  => '',                // J: Blank
            10 => '',                // K: Blank
            11 => $delivery_date,    // L: Delivery Date
        ];

        // Add product rows (aggregated totals)
        foreach ( $product_totals as $product ) {
            $row = $shared;
            $row[12] = $product['code'];                          // M: Product Code
            $row[13] = $this->sanitise_csv_cell( $product['desc'] ); // N: Product Description
            $row[14] = $product['qty'];                           // O: Total Quantity
            $rows[] = $row;
        }

        // Add packaging material rows
        foreach ( $packaging_totals as $pkg ) {
            $row = $shared;
            $row[12] = $pkg['code'];                              // M: Packaging Code
            $row[13] = $this->sanitise_csv_cell( $pkg['desc'] );    // N: Packaging Description
            $row[14] = $pkg['qty'];                               // O: Total Quantity
            $rows[] = $row;
        }

        // ---------------------------------------------------------------
        // Step 5: Convert to CSV string
        // ---------------------------------------------------------------
        return $this->rows_to_csv( $rows );
    }

    /**
     * Sanitise a cell value to prevent CSV injection (Excel formula injection).
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

    /**
     * Convert rows to CSV string with Windows line endings.
     *
     * @param array $rows Array of row arrays
     * @return string CSV content
     */
    private function rows_to_csv( array $rows ) {
        $output = fopen( 'php://temp', 'r+' );

        foreach ( $rows as $row ) {
            fputcsv( $output, $row );
        }

        rewind( $output );
        $csv = stream_get_contents( $output );
        fclose( $output );

        // Windows-style line endings
        $csv = str_replace( "\n", "\r\n", $csv );
        $csv = str_replace( "\r\r\n", "\r\n", $csv );

        return $csv;
    }
}
