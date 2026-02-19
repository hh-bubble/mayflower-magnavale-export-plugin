#!/usr/bin/env bash
# ===========================================================================
# TEST 02: Order Creation — All Products, All Scenarios
# Creates test orders across every product and order type
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "02 — Order Creation"

# ── Track created orders for later tests ───────────────────────────────────
CREATED_ORDERS=()

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 1: Single item orders (one per product category)
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 1: Single item orders..."

# One dim sum product
set_random_address
order_id=$(create_tagged_test_order "single_dimsum" "12CHARSIU:1")
assert_not_empty "Single dim sum order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# One noodle tray
set_random_address
order_id=$(create_tagged_test_order "single_noodletray" "BBNT12400:1")
assert_not_empty "Single noodle tray order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# One sauce pot (ambient)
set_random_address
order_id=$(create_tagged_test_order "single_saucepot" "CBBS12400:1")
assert_not_empty "Single sauce pot order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# One sauce jar (ambient)
set_random_address
order_id=$(create_tagged_test_order "single_saucejar" "SCS12180:1")
assert_not_empty "Single sauce jar order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# One dry mix
set_random_address
order_id=$(create_tagged_test_order "single_drymix" "CSM12255A:1")
assert_not_empty "Single dry mix order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# One battered product
set_random_address
order_id=$(create_tagged_test_order "single_battered" "CB41:1")
assert_not_empty "Single battered product order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# One roll product
set_random_address
order_id=$(create_tagged_test_order "single_rolls" "PRD6040:1")
assert_not_empty "Single rolls order created" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 2: Multi-item orders
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 2: Multi-item orders..."

# Mixed frozen items
set_random_address
order_id=$(create_tagged_test_order "multi_frozen" \
    "12CHARSIU:2" "12HAKOUWP:1" "CB41:3" "KP41:2")
assert_not_empty "Multi frozen items order" "$order_id"
CREATED_ORDERS+=("$order_id")

# Mixed ambient items
set_random_address
order_id=$(create_tagged_test_order "multi_ambient" \
    "CSM12255A:2" "CCCO12180:1" "HSS12180:1" "SS30227:3")
assert_not_empty "Multi ambient items order" "$order_id"
CREATED_ORDERS+=("$order_id")

# Mixed frozen AND ambient
set_random_address
order_id=$(create_tagged_test_order "mixed_frozen_ambient" \
    "SSCB12255:2" "BC12227:1" "SCS12180:1" "CSMH12255A:2")
assert_not_empty "Mixed frozen + ambient order" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 3: Large quantity orders
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 3: Large quantity orders..."

# Large single product quantity
set_random_address
order_id=$(create_tagged_test_order "large_qty_single" "EFR12227:20")
assert_not_empty "Large quantity single product order" "$order_id"
CREATED_ORDERS+=("$order_id")

# Large mixed order (simulating catering)
set_random_address
order_id=$(create_tagged_test_order "large_catering" \
    "BR16200:10" "CC12227:10" "CKP16300:5" "SFR12227:8" "CB41:6")
assert_not_empty "Large catering order" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 4: Every single product (complete coverage)
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 4: Complete product coverage order..."
set_random_address

# Build args array — one of every food product
COMPLETE_ARGS=()
for sku in "${FOOD_SKUS[@]}"; do
    COMPLETE_ARGS+=("${sku}:1")
done

order_id=$(create_tagged_test_order "complete_product_coverage" "${COMPLETE_ARGS[@]}")
assert_not_empty "Complete coverage order (all ${#FOOD_SKUS[@]} products)" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 5: Duplicate product same order
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 5: Duplicate SKU handling..."
set_random_address
order_id=$(create_tagged_test_order "duplicate_sku" "CB41:2" "CB41:3")
assert_not_empty "Duplicate SKU in same order" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 6: Noodle trays only (should all get same packaging type)
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 6: All noodle trays..."
set_random_address
order_id=$(create_tagged_test_order "all_noodle_trays" \
    "BBNT12400:1" "CMNT12400:1" "SCSNT12400:1" "SPCNT12400:1")
assert_not_empty "All noodle trays order" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 7: Sauce/dry products only (ambient — may not need ice)
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 7: Ambient-only order..."
set_random_address
order_id=$(create_tagged_test_order "ambient_only" \
    "CSSS12400:2" "SZS12400:1" "SFS12400:1" "CBBS12400:1" \
    "CSTS12400:1" "CSM12255A:3" "SSGM1454:2")
assert_not_empty "Ambient-only order (no ice needed)" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 8: All dim sum varieties
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 8: All dim sum varieties..."
set_random_address
order_id=$(create_tagged_test_order "all_dimsum" \
    "12CHARSIU:2" "12HAKOUWP:2" "12SIUMAI:2" "12WONTON:2")
assert_not_empty "All dim sum varieties order" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 9: Minimal order (quantity 1 of cheapest item)
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 9: Minimal order..."
set_random_address
order_id=$(create_tagged_test_order "minimal_order" "CSM12255A:1")
assert_not_empty "Minimal order (1x cheapest)" "$order_id"
CREATED_ORDERS+=("$order_id")

# ══════════════════════════════════════════════════════════════════════════
# SCENARIO 10: Products with similar names (regression)
# ══════════════════════════════════════════════════════════════════════════
log_info "SCENARIO 10: Similar name products..."
set_random_address
# Both curry sauce mixes (original vs extra hot, retail vs catering sizes)
order_id=$(create_tagged_test_order "similar_names" \
    "CSM12255A:1" "CSMA1454:1" "CSMH12255A:1" "CSMAH1454:1" \
    "SSGM12255A:1" "SSGM1454:1")
assert_not_empty "Similar name products order" "$order_id"
CREATED_ORDERS+=("$order_id")

# ── Summary ────────────────────────────────────────────────────────────────
log_info "Created ${#CREATED_ORDERS[@]} test orders: ${CREATED_ORDERS[*]}"

# Save order IDs for subsequent test scripts
ORDER_IDS_FILE="${SUITE_DIR}/logs/test_order_ids.txt"
printf '%s\n' "${CREATED_ORDERS[@]}" > "$ORDER_IDS_FILE"
log_info "Order IDs saved to: $ORDER_IDS_FILE"

end_suite
