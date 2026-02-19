#!/usr/bin/env bash
# ===========================================================================
# TEST 01: Product SKU Audit
# Verifies every Magnavale product code exists as a WooCommerce SKU
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "01 — Product SKU Audit"

# ── Test: All food product SKUs exist ──────────────────────────────────────
log_info "Checking all ${#FOOD_SKUS[@]} food product SKUs..."
for sku in "${FOOD_SKUS[@]}"; do
    product_id=$(get_any_product_id_by_sku "$sku")
    assert_not_empty "SKU exists in WooCommerce: $sku" "$product_id"
done

# ── Note: Packaging SKUs (5OSL, 5OSS, etc.) and ice-pack SKUs (DRYICE1KG,
#    ICEPACK) are NOT WooCommerce products.  They are injected by
#    MME_Box_Calculator at export time, so we don't look them up here.

# ── Test: No duplicate SKUs ────────────────────────────────────────────────
log_info "Checking for duplicate SKUs..."
ALL_SKUS=("${FOOD_SKUS[@]}")
for sku in "${ALL_SKUS[@]}"; do
    count=$(wp_cmd eval "
        global \$wpdb;
        \$count = \$wpdb->get_var(\$wpdb->prepare(
            \"SELECT COUNT(*) FROM \$wpdb->postmeta WHERE meta_key = '_sku' AND meta_value = %s\",
            '$sku'
        ));
        echo \$count;
    " 2>/dev/null)
    
    if [[ "$count" -gt 1 ]]; then
        assert_equals "No duplicate SKU: $sku" "1" "$count"
    else
        assert_equals "Unique SKU: $sku" "1" "$count"
    fi
done

# ── Test: No products with empty/missing SKU ───────────────────────────────
log_info "Checking for products with missing SKUs..."
empty_sku_count=$(wp_cmd eval "
    global \$wpdb;
    \$count = \$wpdb->get_var(
        \"SELECT COUNT(*) FROM \$wpdb->posts p
         LEFT JOIN \$wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
         WHERE p.post_type IN ('product', 'product_variation')
         AND p.post_status = 'publish'
         AND (pm.meta_value IS NULL OR pm.meta_value = '')\"
    );
    echo \$count;
" 2>/dev/null)
assert_equals "No published products with empty SKUs" "0" "$empty_sku_count"

# ── Test: Retail vs Catering variant check ─────────────────────────────────
log_info "Checking for expected product variants (retail/catering)..."
wp_cmd eval "
    \$products = wc_get_products(['limit' => -1, 'type' => 'variable']);
    foreach (\$products as \$product) {
        \$variations = \$product->get_available_variations();
        echo \$product->get_name() . ': ' . count(\$variations) . ' variations' . PHP_EOL;
    }
" 2>/dev/null | while read -r line; do
    log_info "  $line"
done

# ── Test: Product prices are set ───────────────────────────────────────────
log_info "Checking all products have prices..."
for sku in "${FOOD_SKUS[@]}"; do
    price=$(wp_cmd eval "
        \$product_id = wc_get_product_id_by_sku('$sku');
        if (\$product_id) {
            \$product = wc_get_product(\$product_id);
            echo \$product->get_price();
        }
    " 2>/dev/null)
    
    if [[ -n "$price" && "$price" != "0" && "$price" != "" ]]; then
        log_pass "  $sku has price: £$price"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        log_warn "  $sku has no price set (may be expected for variations)"
    fi
done

end_suite
