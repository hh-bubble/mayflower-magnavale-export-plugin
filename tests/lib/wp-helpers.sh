#!/usr/bin/env bash
# ===========================================================================
# WP-CLI Helpers — Order creation, product lookup, cleanup
# ===========================================================================

# Source framework if not already loaded
# Use _WPH_DIR internally to avoid clobbering the caller's SCRIPT_DIR
_WPH_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -z "${TESTS_RUN:-}" ]]; then
    source "${_WPH_DIR}/test-framework.sh"
fi

# ── All Magnavale product SKUs ─────────────────────────────────────────────
# Food products (retail/online shop)
FOOD_SKUS=(
    "12CHARSIU"   "12HAKOUWP"   "12SIUMAI"    "12WONTON"
    "BBNT12400"   "BC12227"     "BCBB16300"   "BR16200"
    "CB41"        "CBB41"       "CBBQR12350"  "CBBS12400"
    "CC12227"     "CCCO12180"   "CKP16300"    "CM12227"
    "CMNT12400"   "CNC12330"    "CS30227"     "CSB16300"
    "CSM12255A"   "CSMA1454"    "CSMAH1454"   "CSMH12255A"
    "CSSS12400"   "CSTS12400"   "EFR12227"    "GNC12330"
    "HKPB41"      "HSS12180"    "KP41"        "PRD6040"
    "PT8035"      "SCS12180"    "SCSNT12400"  "SFR12227"
    "SFS12400"    "SNPR12280"   "SPCNT12400"  "SS30227"
    "SSCB12255"   "SSGM12255A"  "SSGM1454"    "SZS12400"
    "VSR6040"
)

# Packaging SKUs (auto-added by plugin, not ordered by customers)
PACKAGING_SKUS=(
    "5OSL"    # Online Shop Box Large
    "5OSLI"   # Large Insert - Top
    "5OSLIS"  # Large Insert - Sides
    "5OSS"    # Online Shop Box Small
    "5OSSI"   # Small Insert - Top
    "5OSSIS"  # Small Insert - Sides
)

# Ice pack SKUs (auto-added)
ICE_PACK_SKUS=(
    "DRYICE1KG"
    "ICEPACK"
)

# Categories for targeted testing
FROZEN_PRODUCTS=("12CHARSIU" "12HAKOUWP" "12SIUMAI" "12WONTON" "BBNT12400" "BCBB16300"
                 "BR16200" "CB41" "CBB41" "CBBQR12350" "CC12227" "CKP16300" "CM12227"
                 "CMNT12400" "CNC12330" "CSB16300" "EFR12227" "GNC12330" "HKPB41"
                 "KP41" "PRD6040" "PT8035" "SCSNT12400" "SFR12227" "SNPR12280"
                 "SPCNT12400" "SSCB12255" "VSR6040" "BC12227")

AMBIENT_PRODUCTS=("CBBS12400" "CCCO12180" "CS30227" "CSM12255A" "CSMA1454" "CSMAH1454"
                  "CSMH12255A" "CSSS12400" "CSTS12400" "HSS12180" "SCS12180" "SFS12400"
                  "SS30227" "SSGM12255A" "SSGM1454" "SZS12400")

NOODLE_TRAYS=("BBNT12400" "CMNT12400" "SCSNT12400" "SPCNT12400")
SAUCE_POTS=("CBBS12400" "CSSS12400" "CSTS12400" "SFS12400" "SZS12400")
SAUCE_JARS=("CCCO12180" "HSS12180" "SCS12180")
DRY_MIXES=("CSM12255A" "CSMA1454" "CSMAH1454" "CSMH12255A" "SSGM12255A" "SSGM1454")
DIM_SUM=("12CHARSIU" "12HAKOUWP" "12SIUMAI" "12WONTON")
BATTERED=("CB41" "CBB41" "HKPB41" "KP41")
ROLLS=("PRD6040" "VSR6040")

# ── Product lookup ─────────────────────────────────────────────────────────
get_product_id_by_sku() {
    local sku="$1"
    wp_cmd post list --post_type=product --meta_key=_sku --meta_value="$sku" --field=ID | head -1
}

get_variation_id_by_sku() {
    local sku="$1"
    wp_cmd post list --post_type=product_variation --meta_key=_sku --meta_value="$sku" --field=ID | head -1
}

get_any_product_id_by_sku() {
    local sku="$1"
    local id
    # Try simple product first
    id=$(get_product_id_by_sku "$sku")
    if [[ -z "$id" ]]; then
        # Try variation
        id=$(get_variation_id_by_sku "$sku")
    fi
    echo "$id"
}

# ── Order creation ─────────────────────────────────────────────────────────
# Create a WooCommerce order with given line items
# Usage: create_test_order "sku1:qty1" "sku2:qty2" ...
# Returns: order ID
create_test_order() {
    local items=("$@")
    local customer_first="${TEST_FIRST_NAME:-Test}"
    local customer_last="${TEST_LAST_NAME:-Customer}"
    local customer_email="${TEST_EMAIL:-test@mayflower-test.example.com}"
    local address_1="${TEST_ADDRESS:-123 Test Street}"
    local city="${TEST_CITY:-Manchester}"
    local postcode="${TEST_POSTCODE:-M1 1AA}"
    local phone="${TEST_PHONE:-07700900000}"

    # Create the order
    local order_id
    order_id=$(wp_cmd wc shop_order create \
        --status=processing \
        --billing='{"first_name":"'"$customer_first"'","last_name":"'"$customer_last"'","email":"'"$customer_email"'","phone":"'"$phone"'","address_1":"'"$address_1"'","city":"'"$city"'","postcode":"'"$postcode"'","country":"GB"}' \
        --shipping='{"first_name":"'"$customer_first"'","last_name":"'"$customer_last"'","address_1":"'"$address_1"'","city":"'"$city"'","postcode":"'"$postcode"'","country":"GB"}' \
        --user=1 \
        --porcelain 2>/dev/null)

    if [[ -z "$order_id" ]]; then
        log_fail "Failed to create order" >&2
        return 1
    fi

    # Tag as test order
    wp_cmd post meta update "$order_id" "_mvtest_order" "1" > /dev/null

    # Add line items via PHP for reliability
    for item in "${items[@]}"; do
        local sku="${item%%:*}"
        local qty="${item##*:}"
        
        wp_cmd eval "
            \$order = wc_get_order($order_id);
            if (!\$order) { echo 'ORDER_NOT_FOUND'; exit; }

            // Find product by SKU
            \$product_id = wc_get_product_id_by_sku('$sku');
            if (!\$product_id) { echo 'SKU_NOT_FOUND:$sku'; exit; }

            \$product = wc_get_product(\$product_id);
            if (!\$product) { echo 'PRODUCT_NOT_FOUND:$sku'; exit; }

            \$item = new WC_Order_Item_Product();
            \$item->set_product(\$product);
            \$item->set_quantity($qty);
            \$item->set_subtotal(\$product->get_price() * $qty);
            \$item->set_total(\$product->get_price() * $qty);
            \$order->add_item(\$item);
            \$order->calculate_totals();
            \$order->save();
            echo 'OK';
        " > /dev/null 2>&1
    done

    echo "$order_id"
}

# Create order and return both ID and metadata
create_tagged_test_order() {
    local scenario_name="$1"
    shift
    local order_id
    order_id=$(create_test_order "$@")

    if [[ -n "$order_id" ]]; then
        wp_cmd post meta update "$order_id" "_mvtest_scenario" "$scenario_name" > /dev/null
        wp_cmd post meta update "$order_id" "_magnavale_export_status" "pending" > /dev/null
        log_info "Created order #${order_id} for scenario: ${scenario_name}" >&2
    fi

    echo "$order_id"
}

# ── CSV Generation (uses real plugin pipeline) ────────────────────────────
# Generate the Magnavale order CSV for one or more orders.
# Usage: generate_test_csv "id1,id2,..."   — returns CSV text on stdout
generate_test_csv() {
    local order_ids="$1"  # Comma-separated
    wp_cmd eval "
        \$ids = array_map('intval', explode(',', '${order_ids}'));
        \$orders = [];
        foreach (\$ids as \$id) { \$o = wc_get_order(\$id); if (\$o) \$orders[] = \$o; }
        if (empty(\$orders)) { echo 'NO_ORDERS'; exit; }
        \$date_calc = new MME_Delivery_Date_Calculator();
        \$box_calc  = new MME_Box_Calculator();
        \$delivery_dates = []; \$box_data = [];
        foreach (\$orders as \$order) {
            \$delivery_dates[\$order->get_id()] = \$date_calc->calculate(\$order);
            \$box_data[\$order->get_id()]       = \$box_calc->calculate(\$order);
        }
        \$csv_builder = new MME_CSV_Builder();
        echo \$csv_builder->build(\$orders, \$delivery_dates, \$box_data);
    " 2>/dev/null
}

# Generate the packing-list CSV for one or more orders.
generate_test_packing_csv() {
    local order_ids="$1"
    wp_cmd eval "
        \$ids = array_map('intval', explode(',', '${order_ids}'));
        \$orders = [];
        foreach (\$ids as \$id) { \$o = wc_get_order(\$id); if (\$o) \$orders[] = \$o; }
        if (empty(\$orders)) { echo 'NO_ORDERS'; exit; }
        \$date_calc = new MME_Delivery_Date_Calculator();
        \$box_calc  = new MME_Box_Calculator();
        \$delivery_dates = []; \$box_data = [];
        foreach (\$orders as \$order) {
            \$delivery_dates[\$order->get_id()] = \$date_calc->calculate(\$order);
            \$box_data[\$order->get_id()]       = \$box_calc->calculate(\$order);
        }
        \$packing = new MME_Packing_List_Builder();
        echo \$packing->build(\$orders, \$delivery_dates, \$box_data);
    " 2>/dev/null
}

# Return the box calculation JSON for a single order.
get_box_data() {
    local order_id="$1"
    wp_cmd eval "
        \$order = wc_get_order($order_id);
        if (!\$order) { echo 'NO_ORDER'; exit; }
        \$box_calc = new MME_Box_Calculator();
        echo json_encode(\$box_calc->calculate(\$order));
    " 2>/dev/null
}

# ── Cleanup ────────────────────────────────────────────────────────────────
cleanup_test_orders() {
    log_info "Cleaning up test orders..."
    local test_orders
    test_orders=$(wp_cmd post list --post_type=shop_order --meta_key=_mvtest_order --meta_value=1 --field=ID 2>/dev/null)
    
    local count=0
    for order_id in $test_orders; do
        wp_cmd post delete "$order_id" --force > /dev/null 2>&1
        count=$((count + 1))
    done
    
    # Also clean up any test CSV files
    find /tmp -name "TEST_*.csv" -mmin +5 -delete 2>/dev/null || true
    
    log_info "Cleaned up $count test orders"
}

# ── Address Generators (for varied test data) ──────────────────────────────
generate_addresses() {
    # Returns a set of varied UK addresses for testing
    cat << 'ADDRESSES'
John|Smith|john.smith@example.com|07700900001|10 Downing Street|London|SW1A 2AA
Jane|Doe|jane.doe@example.com|07700900002|221B Baker Street|London|NW1 6XE
Bob|Jones|bob.jones@example.com|07700900003|1 Royal Mile|Edinburgh|EH1 1SG
Alice|Williams|alice@example.com|07700900004|5 Cardiff Bay|Cardiff|CF10 4PA
Charlie|Brown|charlie@example.com|07700900005|15 Deansgate|Manchester|M3 4LQ
Eve|Taylor|eve.taylor@example.com|07700900006|42 Bold Street|Liverpool|L1 4DS
Frank|Wilson|frank@example.com|07700900007|8 Grey Street|Newcastle|NE1 6EE
Grace|Martin|grace@example.com|07700900008|3 Royal Crescent|Bath|BA1 2LR
ADDRESSES
}

# Generate a single random address and set TEST_* vars
set_random_address() {
    local addresses
    addresses=$(generate_addresses)
    local line
    line=$(echo "$addresses" | shuf -n 1)
    
    IFS='|' read -r TEST_FIRST_NAME TEST_LAST_NAME TEST_EMAIL TEST_PHONE TEST_ADDRESS TEST_CITY TEST_POSTCODE <<< "$line"
    export TEST_FIRST_NAME TEST_LAST_NAME TEST_EMAIL TEST_PHONE TEST_ADDRESS TEST_CITY TEST_POSTCODE
}
