<?php
/**
 * MME_Delivery_Date_Calculator
 *
 * Calculates the despatch and delivery dates for an order based on
 * King Asia's agreed cut-off windows with Magnavale/DPD.
 *
 * CUT-OFF WINDOW LOGIC:
 * =====================
 * | Order Placed Between          | Despatch Day | Delivery Date              |
 * |-------------------------------|--------------|----------------------------|
 * | Wednesday 16:00 → Monday 15:59 | Tuesday      | Wednesday (before 12:00)   |
 * | Monday 16:00 → Tuesday 15:59   | Wednesday    | Thursday (before 12:00)    |
 * | Tuesday 16:00 → Wednesday 15:59| Thursday     | Friday (before 12:00)      |
 *
 * All deliveries use DPD 12:00 service (service code: 1^12)
 *
 * PACKING DATE:
 * The packing date is the despatch day (the day before delivery).
 * Format for packing CSV: "Packing DD.MM.YY"
 *
 * OUTSTANDING QUESTIONS:
 * - Are there Saturday/Sunday/Monday deliveries or only Wed/Thu/Fri?
 *   Current assumption: Wed/Thu/Fri only
 * - What happens with bank holidays? Currently not handled.
 *
 * @package MayflowerMagnavaleExport
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MME_Delivery_Date_Calculator {

    /**
     * The cut-off hour (24h format). Orders placed at or after this hour
     * move into the next cut-off window.
     *
     * @var int
     */
    private $cutoff_hour = 16; // 4:00 PM

    /**
     * The cut-off minute.
     *
     * @var int
     */
    private $cutoff_minute = 0;

    /**
     * Constructor — allows overriding cut-off time from plugin settings.
     */
    public function __construct() {
        $cutoff_time = get_option( 'mme_cutoff_time', '16:00' );
        $parts = explode( ':', $cutoff_time );
        $this->cutoff_hour   = intval( $parts[0] );
        $this->cutoff_minute = intval( $parts[1] ?? 0 );
    }

    /**
     * Calculate delivery and packing dates for a given order.
     *
     * @param WC_Order $order The WooCommerce order object
     * @return array {
     *     @type string $delivery_date  Delivery date in DD/MM/YYYY format (for CSV)
     *     @type string $packing_date   Packing date as "Packing DD.MM.YY" (for packing CSV cols C & D)
     *     @type string $despatch_date  Despatch/packing date in Y-m-d format (internal use)
     * }
     */
    public function calculate( $order ) {
        // Get the order creation date as a DateTime object
        $order_date = $order->get_date_created();

        if ( ! $order_date ) {
            // Fallback: if no creation date, use current time
            // This shouldn't happen in normal flow
            $order_date = new WC_DateTime( 'now', new DateTimeZone( 'Europe/London' ) );
        }

        // Work in UK timezone since that's where the business operates
        $dt = clone $order_date;
        $dt->setTimezone( new DateTimeZone( 'Europe/London' ) );

        // Determine which cut-off window this order falls into
        $despatch_date = $this->get_despatch_date( $dt );
        $delivery_date = clone $despatch_date;
        $delivery_date->modify( '+1 day' ); // Delivery is always the day after despatch

        return [
            'delivery_date' => $delivery_date->format( 'd/m/Y' ),           // DD/MM/YYYY for order CSV column L
            'packing_date'  => 'Packing ' . $despatch_date->format( 'd.m.y' ), // "Packing DD.MM.YY" for packing CSV cols C & D
            'despatch_date' => $despatch_date->format( 'Y-m-d' ),           // Internal use
        ];
    }

    /**
     * Determine the despatch date based on when the order was placed.
     *
     * Logic:
     *   - If order placed Wed 16:00 through Mon 15:59 → despatch Tuesday
     *   - If order placed Mon 16:00 through Tue 15:59 → despatch Wednesday
     *   - If order placed Tue 16:00 through Wed 15:59 → despatch Thursday
     *
     * @param DateTimeInterface $order_datetime When the order was placed
     * @return DateTime The despatch date
     */
    private function get_despatch_date( $order_datetime ) {
        $day_of_week = intval( $order_datetime->format( 'N' ) ); // 1=Mon, 7=Sun
        $hour        = intval( $order_datetime->format( 'H' ) );
        $minute      = intval( $order_datetime->format( 'i' ) );

        // Is this order BEFORE the cut-off time on its day?
        $before_cutoff = ( $hour < $this->cutoff_hour )
                      || ( $hour === $this->cutoff_hour && $minute < $this->cutoff_minute );

        // Determine the "effective day" — if after cut-off, treat as next day's window
        // But we need to map to the cut-off windows, not just add a day
        //
        // The windows are:
        //   Window A: Wed 16:00 → Mon 15:59 → Despatch TUESDAY
        //   Window B: Mon 16:00 → Tue 15:59 → Despatch WEDNESDAY
        //   Window C: Tue 16:00 → Wed 15:59 → Despatch THURSDAY

        // Build a reference point for "which window am I in?"
        // We'll check day + before/after cutoff to determine the window

        // ===================================================================
        // WINDOW MAPPING TABLE
        // ===================================================================
        // Day (N)  | Before cutoff      | After cutoff (or at cutoff)
        // ---------+--------------------+---------------------------
        // 1 (Mon)  | Window A → Tue     | Window B → Wed
        // 2 (Tue)  | Window B → Wed     | Window C → Thu
        // 3 (Wed)  | Window C → Thu     | Window A → next Tue
        // 4 (Thu)  | Window A → next Tue| Window A → next Tue
        // 5 (Fri)  | Window A → next Tue| Window A → next Tue
        // 6 (Sat)  | Window A → next Tue| Window A → next Tue
        // 7 (Sun)  | Window A → next Tue| Window A → next Tue
        // ===================================================================

        $despatch = new DateTime( $order_datetime->format( 'Y-m-d' ), new DateTimeZone( 'Europe/London' ) );

        switch ( $day_of_week ) {
            case 1: // Monday
                if ( $before_cutoff ) {
                    // Window A → despatch Tuesday (tomorrow)
                    $despatch->modify( 'next Tuesday' );
                    // If today IS Tuesday logic... let's be explicit:
                    $despatch = $this->get_next_weekday( $order_datetime, 2 ); // Tuesday
                } else {
                    // Window B → despatch Wednesday
                    $despatch = $this->get_next_weekday( $order_datetime, 3 ); // Wednesday
                }
                break;

            case 2: // Tuesday
                if ( $before_cutoff ) {
                    // Window B → despatch Wednesday (tomorrow)
                    $despatch = $this->get_next_weekday( $order_datetime, 3 );
                } else {
                    // Window C → despatch Thursday
                    $despatch = $this->get_next_weekday( $order_datetime, 4 );
                }
                break;

            case 3: // Wednesday
                if ( $before_cutoff ) {
                    // Window C → despatch Thursday (tomorrow)
                    $despatch = $this->get_next_weekday( $order_datetime, 4 );
                } else {
                    // Window A → despatch next Tuesday
                    $despatch = $this->get_next_weekday( $order_datetime, 2, true ); // force next week
                }
                break;

            case 4: // Thursday
            case 5: // Friday
            case 6: // Saturday
            case 7: // Sunday
                // All fall into Window A → despatch next Tuesday
                $despatch = $this->get_next_weekday( $order_datetime, 2, true );
                break;
        }

        return $despatch;
    }

    /**
     * Get the next occurrence of a specific weekday from a given date.
     *
     * @param DateTimeInterface $from      Starting date
     * @param int               $target_day Target day of week (1=Mon, 7=Sun)
     * @param bool              $force_next If true, skip to NEXT week even if target is today/tomorrow
     * @return DateTime
     */
    private function get_next_weekday( $from, $target_day, $force_next = false ) {
        $days_map = [ 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
                      5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday' ];

        $result = new DateTime( $from->format( 'Y-m-d' ), new DateTimeZone( 'Europe/London' ) );
        $current_day = intval( $result->format( 'N' ) );

        if ( $current_day === $target_day && ! $force_next ) {
            // Today is the target — but for despatch, we always want the NEXT occurrence
            $result->modify( '+1 week' );
        } else {
            $result->modify( 'next ' . $days_map[ $target_day ] );
        }

        return $result;
    }

    /**
     * Calculate delivery date from the current moment (not from an order).
     * Useful for displaying "next delivery date" on the admin dashboard.
     *
     * @return array Same format as calculate()
     */
    public function get_next_delivery_from_now() {
        $now = new WC_DateTime( 'now', new DateTimeZone( 'Europe/London' ) );
        $mock_order = new class {
            public function get_date_created() {
                return new WC_DateTime( 'now', new DateTimeZone( 'Europe/London' ) );
            }
        };
        return $this->calculate( $mock_order );
    }
}
