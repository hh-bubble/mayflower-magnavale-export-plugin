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
# Returns: order ID (numeric) on stdout
create_test_order() {
    local items=("$@")
    local customer_first="${TEST_FIRST_NAME:-Test}"
    local customer_last="${TEST_LAST_NAME:-Customer}"
    local customer_email="${TEST_EMAIL:-test@mayflower-test.example.com}"
    local address_1="${TEST_ADDRESS:-123 Test Street}"
    local city="${TEST_CITY:-Manchester}"
    local postcode="${TEST_POSTCODE:-M1 1AA}"
    local phone="${TEST_PHONE:-07700900000}"

    # Base64-encode customer fields to safely pass special chars into PHP
    local b64_first b64_last b64_email b64_addr b64_city b64_post b64_phone
    b64_first=$(printf '%s' "$customer_first" | base64)
    b64_last=$(printf '%s' "$customer_last" | base64)
    b64_email=$(printf '%s' "$customer_email" | base64)
    b64_addr=$(printf '%s' "$address_1" | base64)
    b64_city=$(printf '%s' "$city" | base64)
    b64_post=$(printf '%s' "$postcode" | base64)
    b64_phone=$(printf '%s' "$phone" | base64)

    # Build the SKU:qty PHP array literal
    local php_items="["
    local first_item=1
    for item in "${items[@]}"; do
        local sku="${item%%:*}"
        local qty="${item##*:}"
        [[ $first_item -eq 1 ]] && first_item=0 || php_items+=","
        php_items+="['sku'=>'${sku}','qty'=>${qty:-0}]"
    done
    php_items+="]"

    # Create order + add line items in a single wp eval call
    local order_id
    order_id=$(wp_cmd eval "
        \$order = wc_create_order(['status' => 'processing']);
        if (is_wp_error(\$order)) { fwrite(STDERR, 'wc_create_order failed'); exit(1); }

        \$first = base64_decode('${b64_first}');
        \$last  = base64_decode('${b64_last}');
        \$email = base64_decode('${b64_email}');
        \$addr  = base64_decode('${b64_addr}');
        \$city  = base64_decode('${b64_city}');
        \$post  = base64_decode('${b64_post}');
        \$phone = base64_decode('${b64_phone}');

        \$order->set_billing_first_name(\$first);
        \$order->set_billing_last_name(\$last);
        \$order->set_billing_email(\$email);
        \$order->set_billing_phone(\$phone);
        \$order->set_billing_address_1(\$addr);
        \$order->set_billing_city(\$city);
        \$order->set_billing_postcode(\$post);
        \$order->set_billing_country('GB');

        \$order->set_shipping_first_name(\$first);
        \$order->set_shipping_last_name(\$last);
        \$order->set_shipping_address_1(\$addr);
        \$order->set_shipping_city(\$city);
        \$order->set_shipping_postcode(\$post);
        \$order->set_shipping_country('GB');

        // Add line items
        foreach (${php_items} as \$li) {
            \$pid = wc_get_product_id_by_sku(\$li['sku']);
            if (!\$pid) continue;
            \$product = wc_get_product(\$pid);
            if (!\$product) continue;
            \$line = new WC_Order_Item_Product();
            \$line->set_product(\$product);
            \$line->set_quantity(\$li['qty']);
            \$line->set_subtotal(\$product->get_price() * \$li['qty']);
            \$line->set_total(\$product->get_price() * \$li['qty']);
            \$order->add_item(\$line);
        }

        \$order->calculate_totals();
        \$order->update_meta_data('_mvtest_order', '1');
        \$order->save();
        echo \$order->get_id();
    " 2>/dev/null)

    if [[ -z "$order_id" || ! "$order_id" =~ ^[0-9]+$ ]]; then
        log_fail "Failed to create order" >&2
        return 1
    fi

    echo "$order_id"
}

# Create order and return both ID and metadata
create_tagged_test_order() {
    local scenario_name="$1"
    shift
    local order_id
    order_id=$(create_test_order "$@")

    if [[ -n "$order_id" ]]; then
        wp_cmd eval "
            \$order = wc_get_order(${order_id});
            if (\$order) {
                \$order->update_meta_data('_mvtest_scenario', '${scenario_name}');
                \$order->update_meta_data('_magnavale_export_status', 'pending');
                \$order->save();
            }
        " > /dev/null 2>&1
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
    local count
    count=$(wp_cmd eval "
        \$orders = wc_get_orders([
            'meta_key'   => '_mvtest_order',
            'meta_value' => '1',
            'limit'      => -1,
            'return'     => 'ids',
        ]);
        foreach (\$orders as \$id) {
            \$o = wc_get_order(\$id);
            if (\$o) \$o->delete(true);
        }
        echo count(\$orders);
    " 2>/dev/null)

    # Also clean up any test CSV files
    find /tmp -name "TEST_*.csv" -mmin +5 -delete 2>/dev/null || true

    log_info "Cleaned up ${count:-0} test orders"
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
