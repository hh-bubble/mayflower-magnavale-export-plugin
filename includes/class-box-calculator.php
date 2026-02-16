<?php
/**
 * MME_Box_Calculator
 *
 * Calculates the box combination, ice pack requirements, and label count
 * for a given order based on the total number of pieces.
 *
 * BOX TIERS:
 * ==========
 * | Total Pieces | Box Combination           |
 * |--------------|---------------------------|
 * | 1–18         | 1 small box               |
 * | 19–33        | 1 large box               |
 * | 34–51        | 1 small + 1 large          |
 * | 52–66        | 2 large                    |
 * | 67+          | Pattern continues (TBC)    |
 *
 * For 67+ pieces, the assumed pattern is:
 *   - Fill large boxes first (capacity: 33 each)
 *   - If remainder <= 18, use 1 small box
 *   - If remainder > 18, use 1 more large box
 *
 * ICE PACKS PER BOX:
 * ==================
 * | Box Type | Dry Ice | Regular Ice |
 * |----------|---------|-------------|
 * | Small    | 3       | 3           |
 * | Large    | 4       | 5           |
 *
 * LABELS:
 * =======
 * 1 address label per box.
 *
 * PACKAGING MAGNAVALE CODES:
 * ==========================
 * 5OSL   = Online Shop Box Large
 * 5OSLI  = Online Shop Box Large Insert - Top    (1 per large box)
 * 5OSLIS = Online Shop Box Large Insert - Sides  (1 per large box)
 * 5OSS   = Online Shop Box Small
 * 5OSSI  = Online Shop Box Small Insert - Top    (1 per small box)
 * 5OSSIS = Online Shop Box Small Insert - Sides  (1 per small box)
 *
 * Ice/dry ice product codes: TBC — need to request from Magnavale
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Box_Calculator {

    // Box capacities
    const SMALL_BOX_CAPACITY = 18;
    const LARGE_BOX_CAPACITY = 33;

    // Ice packs per box type
    const SMALL_DRY_ICE     = 3;
    const SMALL_REGULAR_ICE = 3;
    const LARGE_DRY_ICE     = 4;
    const LARGE_REGULAR_ICE = 5;

    // Magnavale packaging product codes
    const PKG_LARGE_BOX          = '5OSL';
    const PKG_LARGE_INSERT_TOP   = '5OSLI';
    const PKG_LARGE_INSERT_SIDES = '5OSLIS';
    const PKG_SMALL_BOX          = '5OSS';
    const PKG_SMALL_INSERT_TOP   = '5OSSI';
    const PKG_SMALL_INSERT_SIDES = '5OSSIS';

    // Ice product codes — PLACEHOLDER: need real codes from Magnavale
    // TODO: Replace these with actual Magnavale product codes once confirmed
    const PKG_DRY_ICE     = 'DRYICE1KG';   // Placeholder — request from Magnavale
    const PKG_REGULAR_ICE = 'ICEPACK';      // Placeholder — request from Magnavale

    /**
     * Calculate box combination, labels, and ice packs for a single order.
     *
     * @param WC_Order $order The WooCommerce order
     * @return array {
     *     @type int $total_pieces   Total items across all line items
     *     @type int $small_boxes    Number of small boxes needed
     *     @type int $large_boxes    Number of large boxes needed
     *     @type int $total_labels   Total address labels (1 per box)
     *     @type int $dry_ice        Total dry ice packs needed
     *     @type int $regular_ice    Total regular ice packs needed
     *     @type array $packaging    Array of packaging line items for the packing list
     * }
     */
    public function calculate( $order ) {

        // ---------------------------------------------------------------
        // Step 1: Count total pieces in this order
        // ---------------------------------------------------------------
        // "1 piece" = 1 unit of any product (quantity from the line item)
        $total_pieces = 0;

        foreach ( $order->get_items() as $item ) {
            $qty = intval( $item->get_quantity() );
            if ( $qty > 0 ) {
                $total_pieces += $qty;
            }
        }

        // Edge case: if somehow an order has 0 pieces, return empty
        if ( $total_pieces === 0 ) {
            return $this->empty_result();
        }

        // ---------------------------------------------------------------
        // Step 2: Determine box combination
        // ---------------------------------------------------------------
        $boxes = $this->determine_boxes( $total_pieces );

        // ---------------------------------------------------------------
        // Step 3: Calculate ice packs based on box combination
        // ---------------------------------------------------------------
        $dry_ice     = ( $boxes['small'] * self::SMALL_DRY_ICE )     + ( $boxes['large'] * self::LARGE_DRY_ICE );
        $regular_ice = ( $boxes['small'] * self::SMALL_REGULAR_ICE ) + ( $boxes['large'] * self::LARGE_REGULAR_ICE );

        // ---------------------------------------------------------------
        // Step 4: Labels — 1 per box
        // ---------------------------------------------------------------
        $total_labels = $boxes['small'] + $boxes['large'];

        // ---------------------------------------------------------------
        // Step 5: Build packaging line items for the packing list CSV
        // ---------------------------------------------------------------
        // Each box type needs the box itself + insert top + insert sides
        $packaging = [];

        if ( $boxes['large'] > 0 ) {
            $packaging[] = [ 'code' => self::PKG_LARGE_BOX,          'desc' => 'Online Shop Box Large',                'qty' => $boxes['large'] ];
            $packaging[] = [ 'code' => self::PKG_LARGE_INSERT_TOP,   'desc' => 'Online Shop Box Large Insert - Top',    'qty' => $boxes['large'] ];
            $packaging[] = [ 'code' => self::PKG_LARGE_INSERT_SIDES, 'desc' => 'Online Shop Box Large Insert - Sides',  'qty' => $boxes['large'] ];
        }

        if ( $boxes['small'] > 0 ) {
            $packaging[] = [ 'code' => self::PKG_SMALL_BOX,          'desc' => 'Online Shop Box Small',                'qty' => $boxes['small'] ];
            $packaging[] = [ 'code' => self::PKG_SMALL_INSERT_TOP,   'desc' => 'Online Shop Box Small Insert - Top',    'qty' => $boxes['small'] ];
            $packaging[] = [ 'code' => self::PKG_SMALL_INSERT_SIDES, 'desc' => 'Online Shop Box Small Insert - Sides',  'qty' => $boxes['small'] ];
        }

        // Add ice packs as packaging items
        if ( $dry_ice > 0 ) {
            $packaging[] = [ 'code' => self::PKG_DRY_ICE,     'desc' => 'Dry Ice 1kg',   'qty' => $dry_ice ];
        }
        if ( $regular_ice > 0 ) {
            $packaging[] = [ 'code' => self::PKG_REGULAR_ICE,  'desc' => 'Ice Pack',      'qty' => $regular_ice ];
        }

        return [
            'total_pieces'  => $total_pieces,
            'small_boxes'   => $boxes['small'],
            'large_boxes'   => $boxes['large'],
            'total_labels'  => $total_labels,
            'dry_ice'       => $dry_ice,
            'regular_ice'   => $regular_ice,
            'packaging'     => $packaging,
        ];
    }

    /**
     * Determine the number of small and large boxes needed for a given piece count.
     *
     * Tier logic:
     *   1–18   → 1 small
     *   19–33  → 1 large
     *   34–51  → 1 small + 1 large
     *   52–66  → 2 large
     *   67+    → Fill large boxes (33 each), use small for remainder ≤18
     *
     * @param int $pieces Total number of pieces
     * @return array { 'small' => int, 'large' => int }
     */
    private function determine_boxes( $pieces ) {

        // Tier 1: 1–18 pieces → 1 small box
        if ( $pieces <= self::SMALL_BOX_CAPACITY ) {
            return [ 'small' => 1, 'large' => 0 ];
        }

        // Tier 2: 19–33 pieces → 1 large box
        if ( $pieces <= self::LARGE_BOX_CAPACITY ) {
            return [ 'small' => 0, 'large' => 1 ];
        }

        // Tier 3: 34–51 pieces → 1 small + 1 large
        if ( $pieces <= self::SMALL_BOX_CAPACITY + self::LARGE_BOX_CAPACITY ) {
            return [ 'small' => 1, 'large' => 1 ];
        }

        // Tier 4: 52–66 pieces → 2 large
        if ( $pieces <= 2 * self::LARGE_BOX_CAPACITY ) {
            return [ 'small' => 0, 'large' => 2 ];
        }

        // Tier 5: 67+ pieces → pattern continues
        // Fill large boxes first, small for remainder if ≤ 18
        // TODO: Confirm this logic with King Asia (outstanding question #8)
        $large_boxes = intdiv( $pieces, self::LARGE_BOX_CAPACITY );
        $remainder   = $pieces % self::LARGE_BOX_CAPACITY;

        if ( $remainder === 0 ) {
            return [ 'small' => 0, 'large' => $large_boxes ];
        } elseif ( $remainder <= self::SMALL_BOX_CAPACITY ) {
            return [ 'small' => 1, 'large' => $large_boxes ];
        } else {
            // Remainder is between 19 and 32 — needs another large box
            return [ 'small' => 0, 'large' => $large_boxes + 1 ];
        }
    }

    /**
     * Return an empty result for orders with 0 pieces.
     *
     * @return array
     */
    private function empty_result() {
        return [
            'total_pieces'  => 0,
            'small_boxes'   => 0,
            'large_boxes'   => 0,
            'total_labels'  => 0,
            'dry_ice'       => 0,
            'regular_ice'   => 0,
            'packaging'     => [],
        ];
    }
}
