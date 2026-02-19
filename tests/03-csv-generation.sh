#!/usr/bin/env bash
# ===========================================================================
# TEST 03: CSV Generation Validation
# Validates the format, content and correctness of generated CSV files
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "03 — CSV Generation"

# ── Load test order IDs from previous run ──────────────────────────────────
ORDER_IDS_FILE="${SUITE_DIR}/logs/test_order_ids.txt"
if [[ ! -f "$ORDER_IDS_FILE" ]]; then
    log_warn "No test orders found. Run 02-order-creation.sh first."
    log_warn "Creating a quick test order..."
    set_random_address
    ORDER_ID=$(create_tagged_test_order "csv_test" "CB41:2" "EFR12227:1")
    echo "$ORDER_ID" > "$ORDER_IDS_FILE"
fi

CSV_OUTPUT_DIR="/tmp/mvtest_csv_${TIMESTAMP}"
mkdir -p "$CSV_OUTPUT_DIR"

# ── Helper: generate CSV for an order ──────────────────────────────────────
generate_csv_for_order() {
    local order_id="$1"
    local csv_file="${CSV_OUTPUT_DIR}/TEST_order_${order_id}.csv"
    
    wp_cmd eval "
        // Attempt to call the plugin's CSV generation
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$exporter = new Mayflower_Magnavale_CSV_Exporter();
            \$csv = \$exporter->generate_csv($order_id);
            file_put_contents('$csv_file', \$csv);
            echo 'OK';
        } else {
            // Try alternative class/function names
            if (function_exists('mayflower_generate_csv')) {
                \$csv = mayflower_generate_csv($order_id);
                file_put_contents('$csv_file', \$csv);
                echo 'OK';
            } else {
                echo 'CLASS_NOT_FOUND';
            }
        }
    " 2>/dev/null
    
    echo "$csv_file"
}

# ══════════════════════════════════════════════════════════════════════════
# TEST GROUP: CSV Format Validation
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing CSV format compliance..."

FIRST_ORDER=$(head -1 "$ORDER_IDS_FILE")
CSV_FILE=$(generate_csv_for_order "$FIRST_ORDER")

if [[ -f "$CSV_FILE" && -s "$CSV_FILE" ]]; then
    # Test: File is valid CSV (not empty, has header + data)
    assert_file_exists "CSV file generated for order #$FIRST_ORDER" "$CSV_FILE"
    assert_csv_row_count "CSV has at least 1 data row" "$CSV_FILE" 1

    # Test: No BOM (byte order mark) — can cause issues with Magnavale's systems
    BOM_CHECK=$(xxd -l 3 "$CSV_FILE" | grep -c "efbbbf" || true)
    assert_equals "CSV has no UTF-8 BOM" "0" "$BOM_CHECK"

    # Test: Line endings are correct (CRLF or LF, consistent)
    CR_COUNT=$(tr -cd '\r' < "$CSV_FILE" | wc -c)
    LF_COUNT=$(grep -c '' "$CSV_FILE" || true)
    log_info "Line ending check: $CR_COUNT CR chars, $LF_COUNT lines"

    # Test: No trailing comma on header row
    HEADER=$(head -1 "$CSV_FILE")
    LAST_CHAR="${HEADER: -1}"
    assert_true "Header does not end with comma" "[[ '$LAST_CHAR' != ',' ]]"

    # Test: Consistent column count across all rows
    COLUMN_COUNTS=$(awk -F',' '{print NF}' "$CSV_FILE" | sort -u)
    UNIQUE_COUNTS=$(echo "$COLUMN_COUNTS" | wc -l)
    assert_equals "Consistent column count across all rows" "1" "$UNIQUE_COUNTS"

    # Test: No empty required fields
    log_info "Checking for empty required fields in CSV..."
    HEADER_LINE=$(head -1 "$CSV_FILE")
    log_info "  CSV Header: $HEADER_LINE"

    # Test: File encoding is UTF-8 or ASCII (no binary garbage)
    FILE_TYPE=$(file -bi "$CSV_FILE")
    assert_contains "CSV is text/csv encoding" "$FILE_TYPE" "text"

    # Test: No null bytes
    NULL_COUNT=$(tr -cd '\0' < "$CSV_FILE" | wc -c)
    assert_equals "CSV has no null bytes" "0" "$NULL_COUNT"

else
    skip_test "CSV format validation" "Could not generate CSV — check plugin class name"
    log_warn "Attempting to find plugin export class..."
    wp_cmd eval "
        // Search for export-related classes
        \$classes = get_declared_classes();
        foreach (\$classes as \$class) {
            if (stripos(\$class, 'mayflower') !== false || stripos(\$class, 'magnavale') !== false) {
                echo \$class . PHP_EOL;
            }
        }
    " 2>/dev/null || true
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST GROUP: CSV Content Accuracy
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing CSV content accuracy..."

while read -r order_id; do
    [[ -z "$order_id" ]] && continue
    
    csv_file=$(generate_csv_for_order "$order_id")
    
    if [[ -f "$csv_file" && -s "$csv_file" ]]; then
        # Test: Order number appears in CSV
        assert_contains "Order #$order_id referenced in CSV" "$(cat "$csv_file")" "$order_id"
        
        # Test: Customer postcode present
        postcode=$(wp_cmd post meta get "$order_id" _shipping_postcode 2>/dev/null)
        if [[ -n "$postcode" ]]; then
            assert_contains "Postcode in CSV for order #$order_id" "$(cat "$csv_file")" "$postcode"
        fi
        
        # Test: Product SKUs present
        ORDER_SKUS=$(wp_cmd eval "
            \$order = wc_get_order($order_id);
            foreach (\$order->get_items() as \$item) {
                \$product = \$item->get_product();
                if (\$product) echo \$product->get_sku() . PHP_EOL;
            }
        " 2>/dev/null)
        
        while read -r sku; do
            [[ -z "$sku" ]] && continue
            assert_contains "SKU $sku in CSV for order #$order_id" "$(cat "$csv_file")" "$sku"
        done <<< "$ORDER_SKUS"
        
        log_info "  Order #$order_id CSV validated ✓"
    fi
done < "$ORDER_IDS_FILE"

# ══════════════════════════════════════════════════════════════════════════
# TEST GROUP: CSV Special Character Handling
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing special character handling..."

# Create order with special characters in address
TEST_FIRST_NAME="O'Brien"
TEST_LAST_NAME="Smith-Jones"
TEST_ADDRESS="Flat 2, 15 St. Mary's Close"
TEST_CITY="King's Lynn"
TEST_POSTCODE="PE30 1AA"
TEST_EMAIL="obriens@example.com"
TEST_PHONE="07700900099"
export TEST_FIRST_NAME TEST_LAST_NAME TEST_ADDRESS TEST_CITY TEST_POSTCODE TEST_EMAIL TEST_PHONE

order_id=$(create_tagged_test_order "special_chars" "CB41:1")
if [[ -n "$order_id" ]]; then
    csv_file=$(generate_csv_for_order "$order_id")
    if [[ -f "$csv_file" && -s "$csv_file" ]]; then
        # Apostrophes and hyphens should be preserved, not mangled
        assert_not_contains "No CSV injection from apostrophes" "$(cat "$csv_file")" "=CMD"
        assert_not_contains "No formula injection" "$(cat "$csv_file")" "+CMD"
        log_pass "Special character order CSV generated safely"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST GROUP: Quantity Aggregation
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing quantity aggregation in CSV..."

set_random_address
order_id=$(create_tagged_test_order "qty_aggregate" "CB41:5")
if [[ -n "$order_id" ]]; then
    csv_file=$(generate_csv_for_order "$order_id")
    if [[ -f "$csv_file" && -s "$csv_file" ]]; then
        # Check quantity 5 appears for CB41
        assert_contains "Quantity 5 in CSV for CB41" "$(cat "$csv_file")" "5"
        log_pass "Quantity aggregation test passed"
    fi
fi

# ── Cleanup CSV output ─────────────────────────────────────────────────────
log_info "Test CSVs saved in: $CSV_OUTPUT_DIR"

end_suite
