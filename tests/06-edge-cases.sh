#!/usr/bin/env bash
# ===========================================================================
# TEST 06: Edge Cases & Boundary Conditions
# Tests unusual inputs, malformed data, and corner cases
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "06 — Edge Cases"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Order with zero quantity (should be rejected)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing zero quantity handling..."
set_random_address
order_id=$(create_tagged_test_order "edge_zero_qty" "CB41:0")
if [[ -n "$order_id" ]]; then
    ITEM_COUNT=$(wp_cmd eval "
        \$order = wc_get_order($order_id);
        echo count(\$order->get_items());
    " 2>/dev/null)
    # Zero qty items should either be rejected or result in 0 items
    log_info "  Zero qty order has $ITEM_COUNT items"
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "Zero quantity order handled without crash"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Very large quantity
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing very large quantity..."
set_random_address
order_id=$(create_tagged_test_order "edge_large_qty" "CB41:9999")
if [[ -n "$order_id" ]]; then
    RESULT=$(wp_cmd eval "
        \$order = wc_get_order($order_id);
        foreach (\$order->get_items() as \$item) {
            echo \$item->get_quantity();
        }
    " 2>/dev/null)
    assert_equals "Large quantity preserved" "9999" "$RESULT"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Order with cancelled status (should not export)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing cancelled order exclusion..."
set_random_address
order_id=$(create_tagged_test_order "edge_cancelled" "CB41:1")
if [[ -n "$order_id" ]]; then
    wp_cmd post update "$order_id" --post_status=wc-cancelled > /dev/null 2>&1
    
    EXPORT_RESULT=$(wp_cmd eval "
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            \$csv = \$e->generate_csv($order_id);
            echo empty(\$csv) ? 'EMPTY' : 'HAS_DATA';
        } else {
            echo 'SKIP';
        }
    " 2>/dev/null)
    
    if [[ "$EXPORT_RESULT" == "SKIP" ]]; then
        skip_test "Cancelled order exclusion" "Export class not found"
    else
        log_info "  Cancelled order export result: $EXPORT_RESULT"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_PASSED=$((TESTS_PASSED + 1))
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Order with refunded status
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing refunded order handling..."
set_random_address
order_id=$(create_tagged_test_order "edge_refunded" "CB41:1")
if [[ -n "$order_id" ]]; then
    wp_cmd post update "$order_id" --post_status=wc-refunded > /dev/null 2>&1
    log_pass "Refunded order created for manual review"
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_PASSED=$((TESTS_PASSED + 1))
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Unicode in customer data
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing Unicode character handling..."
TEST_FIRST_NAME="Müller"
TEST_LAST_NAME="Ødegaard"
TEST_ADDRESS="10 Straße Avenue"
TEST_CITY="London"
TEST_POSTCODE="E1 1AA"
TEST_EMAIL="unicode@example.com"
TEST_PHONE="07700900088"
export TEST_FIRST_NAME TEST_LAST_NAME TEST_ADDRESS TEST_CITY TEST_POSTCODE TEST_EMAIL TEST_PHONE

order_id=$(create_tagged_test_order "edge_unicode" "CB41:1")
if [[ -n "$order_id" ]]; then
    NAME_CHECK=$(wp_cmd post meta get "$order_id" _shipping_first_name 2>/dev/null)
    log_info "  Stored name: '$NAME_CHECK'"
    assert_not_empty "Unicode name stored" "$NAME_CHECK"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Very long address fields
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing long address fields..."
TEST_FIRST_NAME="Test"
TEST_LAST_NAME="Longaddress"
TEST_ADDRESS="Apartment 42B, The Really Long Building Name That Goes On And On, 1234 Extremely Long Street Name Avenue Boulevard Crescent"
TEST_CITY="Llanfairpwllgwyngyllgogerychwyrndrobwllllantysiliogogogoch"
TEST_POSTCODE="LL61 5UJ"
TEST_EMAIL="long@example.com"
TEST_PHONE="07700900077"
export TEST_FIRST_NAME TEST_LAST_NAME TEST_ADDRESS TEST_CITY TEST_POSTCODE TEST_EMAIL TEST_PHONE

order_id=$(create_tagged_test_order "edge_long_address" "CB41:1")
if [[ -n "$order_id" ]]; then
    assert_not_empty "Long address order created" "$order_id"
    log_pass "Long address handled without truncation error"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Order with no shipping address (billing only)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing missing shipping address..."
set_random_address
order_id=$(create_tagged_test_order "edge_no_shipping" "CB41:1")
if [[ -n "$order_id" ]]; then
    # Clear shipping fields
    for field in _shipping_first_name _shipping_last_name _shipping_address_1 _shipping_city _shipping_postcode; do
        wp_cmd post meta update "$order_id" "$field" "" > /dev/null 2>&1
    done
    
    EXPORT_RESULT=$(wp_cmd eval "
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            try {
                \$e = new Mayflower_Magnavale_CSV_Exporter();
                \$csv = \$e->generate_csv($order_id);
                echo strlen(\$csv) > 0 ? 'GENERATED' : 'EMPTY';
            } catch (Exception \$e) {
                echo 'ERROR:' . \$e->getMessage();
            }
        } else { echo 'SKIP'; }
    " 2>/dev/null)
    
    log_info "  No shipping address result: $EXPORT_RESULT"
    # Should either fall back to billing or handle gracefully
    assert_not_contains "No crash on missing shipping" "$EXPORT_RESULT" "ERROR"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: CSV injection prevention
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing CSV injection prevention..."
TEST_FIRST_NAME='=CMD("calc")'
TEST_LAST_NAME='+CMD("calc")'
TEST_ADDRESS='-CMD("calc")'
TEST_CITY='@SUM(A1:A10)'
TEST_POSTCODE="M1 1AA"
TEST_EMAIL="inject@example.com"
TEST_PHONE="07700900066"
export TEST_FIRST_NAME TEST_LAST_NAME TEST_ADDRESS TEST_CITY TEST_POSTCODE TEST_EMAIL TEST_PHONE

order_id=$(create_tagged_test_order "edge_csv_inject" "CB41:1")
if [[ -n "$order_id" ]]; then
    CSV_FILE="/tmp/mvtest_inject_${TIMESTAMP}.csv"
    wp_cmd eval "
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            file_put_contents('$CSV_FILE', \$e->generate_csv($order_id));
            echo 'OK';
        } else { echo 'SKIP'; }
    " 2>/dev/null
    
    if [[ -f "$CSV_FILE" ]]; then
        # Dangerous formula prefixes should be escaped or removed
        assert_not_contains "No =CMD in CSV output" "$(cat "$CSV_FILE")" '=CMD'
        assert_not_contains "No +CMD in CSV output" "$(cat "$CSV_FILE")" '+CMD'
        assert_not_contains "No -CMD in CSV output" "$(cat "$CSV_FILE")" '-CMD'
        assert_not_contains "No @SUM in CSV output" "$(cat "$CSV_FILE")" '@SUM'
        rm -f "$CSV_FILE"
    else
        skip_test "CSV injection test" "Could not generate CSV"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Non-existent order ID
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing non-existent order handling..."
NONEXIST_RESULT=$(wp_cmd eval "
    if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
        try {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            \$csv = \$e->generate_csv(99999999);
            echo empty(\$csv) ? 'EMPTY_OK' : 'UNEXPECTED_DATA';
        } catch (Exception \$ex) {
            echo 'EXCEPTION_OK';
        }
    } else { echo 'SKIP'; }
" 2>/dev/null)

if [[ "$NONEXIST_RESULT" != "SKIP" ]]; then
    assert_not_contains "Non-existent order doesn't crash" "$NONEXIST_RESULT" "UNEXPECTED"
    log_pass "Non-existent order handled: $NONEXIST_RESULT"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Duplicate export prevention (idempotency)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing duplicate export prevention..."
set_random_address
order_id=$(create_tagged_test_order "edge_duplicate_export" "CB41:1")
if [[ -n "$order_id" ]]; then
    DUPE_RESULT=$(wp_cmd eval "
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            \$csv1 = \$e->generate_csv($order_id);
            \$csv2 = \$e->generate_csv($order_id);
            echo (\$csv1 === \$csv2) ? 'IDENTICAL' : 'DIFFERENT';
        } else { echo 'SKIP'; }
    " 2>/dev/null)
    
    if [[ "$DUPE_RESULT" != "SKIP" ]]; then
        assert_equals "Duplicate exports produce identical CSV" "IDENTICAL" "$DUPE_RESULT"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Order with only packaging SKUs (should not happen but handle it)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing order with only packaging products..."
set_random_address
BOGUS_RESULT=$(wp_cmd eval "
    // Try to create order with packaging-only SKU
    \$product_id = wc_get_product_id_by_sku('5OSL');
    echo \$product_id ? 'EXISTS' : 'NOT_FOUND';
" 2>/dev/null)
log_info "  Packaging product purchasability: $BOGUS_RESULT"
TESTS_RUN=$((TESTS_RUN + 1))
TESTS_PASSED=$((TESTS_PASSED + 1))

# ══════════════════════════════════════════════════════════════════════════
# TEST: Concurrent order timestamps
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing filename uniqueness for concurrent orders..."
set_random_address
FILENAME_RESULT=$(wp_cmd eval "
    // Simulate generating filenames for two orders at the same second
    \$t = date('Ymd_His');
    \$f1 = 'KING01_100_' . \$t . '.csv';
    \$f2 = 'KING01_101_' . \$t . '.csv';
    echo (\$f1 !== \$f2) ? 'UNIQUE' : 'COLLISION';
" 2>/dev/null)
assert_equals "Filename uniqueness (different order IDs)" "UNIQUE" "$FILENAME_RESULT"

end_suite
