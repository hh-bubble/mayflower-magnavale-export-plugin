#!/usr/bin/env bash
# ===========================================================================
# TEST 10: Regression Cleanup
# Removes all test orders and temporary files
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "10 — Cleanup"

if [[ "$SKIP_CLEANUP" == "1" ]]; then
    skip_test "Cleanup" "SKIP_CLEANUP=1 set — test orders preserved"
    end_suite
    exit 0
fi

# ── Remove all test orders ─────────────────────────────────────────────────
log_info "Finding test orders..."
TEST_ORDERS=$(wp_cmd post list --post_type=shop_order --meta_key=_mvtest_order --meta_value=1 --field=ID 2>/dev/null)
ORDER_COUNT=$(echo "$TEST_ORDERS" | grep -c '[0-9]' || true)
log_info "  Found $ORDER_COUNT test orders to clean up"

DELETED=0
ERRORS=0
for oid in $TEST_ORDERS; do
    [[ -z "$oid" ]] && continue
    if wp_cmd post delete "$oid" --force > /dev/null 2>&1; then
        DELETED=$((DELETED + 1))
    else
        ERRORS=$((ERRORS + 1))
        log_warn "  Failed to delete order #$oid"
    fi
done

assert_equals "All test orders deleted" "0" "$ERRORS"
log_info "  Deleted $DELETED test orders"

# ── Remove temporary CSV files ─────────────────────────────────────────────
log_info "Cleaning up temporary CSV files..."
CSV_CLEANED=0
for pattern in "/tmp/mvtest_*" "/tmp/TEST_*.csv" "/tmp/KING01_TEST_*"; do
    for f in $pattern; do
        [[ -f "$f" ]] && rm -f "$f" && CSV_CLEANED=$((CSV_CLEANED + 1))
    done
    for d in $pattern; do
        [[ -d "$d" ]] && rm -rf "$d" && CSV_CLEANED=$((CSV_CLEANED + 1))
    done
done
log_info "  Cleaned up $CSV_CLEANED temp files/directories"
TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))

# ── Remove test order ID tracking file ─────────────────────────────────────
ORDER_IDS_FILE="${SUITE_DIR}/logs/test_order_ids.txt"
if [[ -f "$ORDER_IDS_FILE" ]]; then
    rm -f "$ORDER_IDS_FILE"
    log_info "  Removed order ID tracking file"
fi

# ── Verify cleanup ─────────────────────────────────────────────────────────
REMAINING=$(wp_cmd post list --post_type=shop_order --meta_key=_mvtest_order --meta_value=1 --field=ID 2>/dev/null | grep -c '[0-9]' || true)
assert_equals "No test orders remaining" "0" "$REMAINING"

log_info "Cleanup complete!"

end_suite
