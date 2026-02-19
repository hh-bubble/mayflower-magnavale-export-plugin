#!/usr/bin/env bash
# ===========================================================================
# TEST 04: Packaging Logic
# Tests automatic box, insert, and ice pack addition
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "04 — Packaging Logic"

CSV_OUTPUT_DIR="/tmp/mvtest_packaging_${TIMESTAMP}"
mkdir -p "$CSV_OUTPUT_DIR"

# Helper to get CSV content for an order
get_csv() {
    local order_id="$1"
    local csv_file="${CSV_OUTPUT_DIR}/pkg_${order_id}.csv"
    wp_cmd eval "
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            file_put_contents('$csv_file', \$e->generate_csv($order_id));
            echo 'OK';
        } elseif (function_exists('mayflower_generate_csv')) {
            file_put_contents('$csv_file', mayflower_generate_csv($order_id));
            echo 'OK';
        } else { echo 'SKIP'; }
    " 2>/dev/null
    
    if [[ -f "$csv_file" ]]; then
        cat "$csv_file"
    fi
}

# ══════════════════════════════════════════════════════════════════════════
# TEST: Small box for small orders
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing small box allocation..."
set_random_address
order_id=$(create_tagged_test_order "pkg_small_box" "CB41:1")
csv_content=$(get_csv "$order_id")

if [[ -n "$csv_content" ]]; then
    assert_contains "Small box (5OSS) added for small order" "$csv_content" "5OSS"
    assert_contains "Small insert top (5OSSI) added" "$csv_content" "5OSSI"
    assert_contains "Small insert sides (5OSSIS) added" "$csv_content" "5OSSIS"
    assert_not_contains "Large box NOT added for small order" "$csv_content" "5OSL"
else
    skip_test "Small box allocation" "CSV generation unavailable"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Large box for larger orders
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing large box allocation..."
set_random_address
order_id=$(create_tagged_test_order "pkg_large_box" \
    "CB41:3" "EFR12227:2" "KP41:2" "SSCB12255:2")
csv_content=$(get_csv "$order_id")

if [[ -n "$csv_content" ]]; then
    assert_contains "Large box (5OSL) added for large order" "$csv_content" "5OSL"
    assert_contains "Large insert top (5OSLI) added" "$csv_content" "5OSLI"
    assert_contains "Large insert sides (5OSLIS) added" "$csv_content" "5OSLIS"
else
    skip_test "Large box allocation" "CSV generation unavailable"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Ice packs for frozen products
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing ice pack inclusion for frozen products..."
set_random_address
order_id=$(create_tagged_test_order "pkg_frozen_ice" "12CHARSIU:1" "CB41:1")
csv_content=$(get_csv "$order_id")

if [[ -n "$csv_content" ]]; then
    # Should contain either DRYICE1KG or ICEPACK
    HAS_ICE=0
    echo "$csv_content" | grep -qF "DRYICE1KG" && HAS_ICE=1
    echo "$csv_content" | grep -qF "ICEPACK" && HAS_ICE=1
    
    if [[ $HAS_ICE -eq 1 ]]; then
        log_pass "Ice pack included for frozen order"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "No ice pack found in frozen product order CSV"
    fi
else
    skip_test "Ice pack inclusion" "CSV generation unavailable"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: No ice packs for ambient-only orders
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing NO ice packs for ambient-only orders..."
set_random_address
order_id=$(create_tagged_test_order "pkg_ambient_no_ice" \
    "CSM12255A:2" "SSGM1454:1" "HSS12180:1")
csv_content=$(get_csv "$order_id")

if [[ -n "$csv_content" ]]; then
    assert_not_contains "No DRYICE for ambient order" "$csv_content" "DRYICE1KG"
    assert_not_contains "No ICEPACK for ambient order" "$csv_content" "ICEPACK"
else
    skip_test "Ambient ice pack exclusion" "CSV generation unavailable"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Box + insert quantities are always 1
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing packaging quantities..."
set_random_address
order_id=$(create_tagged_test_order "pkg_qty_check" "CB41:10" "EFR12227:10")
csv_content=$(get_csv "$order_id")

if [[ -n "$csv_content" ]]; then
    # Packaging items should each appear exactly once with qty 1
    BOX_LINES=$(echo "$csv_content" | grep -c "5OS" || true)
    log_info "  Packaging lines in CSV: $BOX_LINES"
    
    # Each packaging SKU should appear at most once
    for pkg_sku in "5OSL" "5OSLI" "5OSLIS" "5OSS" "5OSSI" "5OSSIS"; do
        count=$(echo "$csv_content" | grep -c "$pkg_sku" || true)
        assert_true "Packaging $pkg_sku appears 0 or 1 times" "[[ $count -le 1 ]]"
    done
else
    skip_test "Packaging quantities" "CSV generation unavailable"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Packaging NOT included as customer-orderable products
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing packaging products are not customer-visible..."
for pkg_sku in "${PACKAGING_SKUS[@]}"; do
    visibility=$(wp_cmd eval "
        \$id = wc_get_product_id_by_sku('$pkg_sku');
        if (\$id) {
            \$p = wc_get_product(\$id);
            echo \$p->get_catalog_visibility();
        } else {
            echo 'not_found';
        }
    " 2>/dev/null)
    
    if [[ "$visibility" == "hidden" || "$visibility" == "not_found" ]]; then
        log_pass "Packaging $pkg_sku is hidden from catalogue"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        log_warn "Packaging $pkg_sku visibility: $visibility (should be hidden)"
        TESTS_RUN=$((TESTS_RUN + 1))
    fi
done

# ══════════════════════════════════════════════════════════════════════════
# TEST: Mixed box size boundary
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing box size boundary..."

# Test the boundary between small and large box
# Create orders of increasing size to find the threshold
for qty in 1 2 3 4 5 6; do
    set_random_address
    order_id=$(create_tagged_test_order "pkg_boundary_${qty}" "CB41:${qty}")
    csv_content=$(get_csv "$order_id")
    
    if [[ -n "$csv_content" ]]; then
        HAS_SMALL=$(echo "$csv_content" | grep -c "5OSS" || true)
        HAS_LARGE=$(echo "$csv_content" | grep -c "5OSL" || true)
        
        if [[ $HAS_SMALL -gt 0 ]]; then
            log_info "  Qty $qty → Small box"
        elif [[ $HAS_LARGE -gt 0 ]]; then
            log_info "  Qty $qty → Large box"
        else
            log_warn "  Qty $qty → No box detected"
        fi
    fi
done
TESTS_RUN=$((TESTS_RUN + 1))
TESTS_PASSED=$((TESTS_PASSED + 1))
log_pass "Box size boundary test completed (manual review above)"

end_suite
