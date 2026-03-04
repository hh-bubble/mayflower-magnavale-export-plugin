#!/usr/bin/env bash
# ===========================================================================
# TEST 12: Frozen Item Ice Pack Logic
# Verifies that ice packs (11ICEPACK) and dry ice (11DRYICE) are ONLY
# included in the packing CSV when the order contains at least one frozen
# product (is_frozen ACF field).
#
# Scenarios tested:
#   1. Ambient-only order           -> NO ice packs, NO dry ice
#   2. Frozen-only order            -> HAS ice packs + dry ice
#   3. Mixed order (frozen+ambient) -> HAS ice packs + dry ice
#   4. Bundle with frozen items     -> HAS ice packs + dry ice
#   5. Ambient-only bundle          -> NO ice packs, NO dry ice
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "12 — Frozen Item Ice Pack Logic"

# ══════════════════════════════════════════════════════════════════════════
# TEST 1: Ambient-only order — NO ice packs, NO dry ice
# Products: sauce mixes + sauce bottles (all ambient)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing ambient-only order has NO ice packs..."
set_random_address
order_id=$(create_tagged_test_order "frozen_test_ambient" \
    "CSM12255A:2" "SSGM12255A:1" "HSS12180:1" "SFS12400:1")

if [[ -n "$order_id" ]]; then
    packing_csv=$(generate_test_packing_csv "$order_id")

    if [[ -n "$packing_csv" && "$packing_csv" != "NO_ORDERS" ]]; then
        HAS_DRYICE=0
        HAS_ICEPACK=0
        echo "$packing_csv" | grep -qF "11DRYICE" && HAS_DRYICE=1
        echo "$packing_csv" | grep -qF "11ICEPACK" && HAS_ICEPACK=1

        assert_equals "No dry ice in ambient-only order" "0" "$HAS_DRYICE"
        assert_equals "No ice packs in ambient-only order" "0" "$HAS_ICEPACK"

        # Boxes and inserts should still be present
        assert_contains "Ambient order still has box" "$packing_csv" "5OSS"
        assert_contains "Ambient order still has insert top" "$packing_csv" "5OSSI"
    else
        skip_test "Ambient-only ice pack test" "Could not generate packing CSV"
    fi

    # Also verify via box data JSON
    BOX_JSON=$(get_box_data "$order_id")
    if [[ -n "$BOX_JSON" && "$BOX_JSON" != "NO_ORDER" ]]; then
        DRY_ICE=$(echo "$BOX_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin)['dry_ice'])" 2>/dev/null || true)
        REG_ICE=$(echo "$BOX_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin)['regular_ice'])" 2>/dev/null || true)
        assert_equals "Ambient order dry_ice=0 in box data" "0" "$DRY_ICE"
        assert_equals "Ambient order regular_ice=0 in box data" "0" "$REG_ICE"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST 2: Frozen-only order — HAS ice packs + dry ice
# Products: dim sum + battered items (all frozen)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing frozen-only order has ice packs..."
set_random_address
order_id=$(create_tagged_test_order "frozen_test_frozen" \
    "12CHARSIU:2" "CB41:3" "EFR12227:1")

if [[ -n "$order_id" ]]; then
    packing_csv=$(generate_test_packing_csv "$order_id")

    if [[ -n "$packing_csv" && "$packing_csv" != "NO_ORDERS" ]]; then
        assert_contains "Dry ice present in frozen order" "$packing_csv" "11DRYICE"
        assert_contains "Ice packs present in frozen order" "$packing_csv" "11ICEPACK"
        assert_contains "Frozen order has box" "$packing_csv" "5OSS"
    else
        skip_test "Frozen-only ice pack test" "Could not generate packing CSV"
    fi

    # Also verify via box data JSON
    BOX_JSON=$(get_box_data "$order_id")
    if [[ -n "$BOX_JSON" && "$BOX_JSON" != "NO_ORDER" ]]; then
        DRY_ICE=$(echo "$BOX_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin)['dry_ice'])" 2>/dev/null || true)
        REG_ICE=$(echo "$BOX_JSON" | python3 -c "import sys,json; print(json.load(sys.stdin)['regular_ice'])" 2>/dev/null || true)
        assert_true "Frozen order dry_ice > 0" "[[ $DRY_ICE -gt 0 ]]"
        assert_true "Frozen order regular_ice > 0" "[[ $REG_ICE -gt 0 ]]"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST 3: Mixed order (frozen + ambient) — HAS ice packs + dry ice
# At least one frozen product means ice packs are included
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing mixed order (frozen + ambient) has ice packs..."
set_random_address
order_id=$(create_tagged_test_order "frozen_test_mixed" \
    "12CHARSIU:1" "CSM12255A:2" "HSS12180:1")

if [[ -n "$order_id" ]]; then
    packing_csv=$(generate_test_packing_csv "$order_id")

    if [[ -n "$packing_csv" && "$packing_csv" != "NO_ORDERS" ]]; then
        assert_contains "Dry ice in mixed order" "$packing_csv" "11DRYICE"
        assert_contains "Ice packs in mixed order" "$packing_csv" "11ICEPACK"
    else
        skip_test "Mixed order ice pack test" "Could not generate packing CSV"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST 4: Bundle containing frozen items — HAS ice packs + dry ice
# Uses Dim Sum Delight Bundle (ID 15041) or Freezer Fillers Bundle (ID 15043)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing bundle with frozen items has ice packs..."

BUNDLE_FROZEN_CHECK=$(wp_cmd eval "
    \$order = wc_create_order(['status' => 'processing']);
    if (is_wp_error(\$order)) { echo 'CREATE_FAILED'; exit; }

    // Try frozen bundles: Dim Sum Delight (15041), Freezer Fillers (15043)
    \$bundle_ids = [15041, 15043];
    \$added = false;
    foreach (\$bundle_ids as \$bid) {
        \$product = wc_get_product(\$bid);
        if (\$product) {
            \$line = new WC_Order_Item_Product();
            \$line->set_product(\$product);
            \$line->set_quantity(1);
            \$line->set_subtotal(\$product->get_price() ?: 10);
            \$line->set_total(\$product->get_price() ?: 10);
            \$order->add_item(\$line);
            \$added = true;
            break;
        }
    }

    if (!\$added) { echo 'NO_BUNDLE'; \$order->delete(true); exit; }

    \$order->set_billing_first_name('Test');
    \$order->set_billing_last_name('FrozenBundle');
    \$order->set_billing_address_1('1 Test St');
    \$order->set_billing_city('Manchester');
    \$order->set_billing_postcode('M1 1AA');
    \$order->set_billing_phone('07700900000');
    \$order->set_billing_email('test@example.com');
    \$order->set_shipping_first_name('Test');
    \$order->set_shipping_last_name('FrozenBundle');
    \$order->set_shipping_address_1('1 Test St');
    \$order->set_shipping_city('Manchester');
    \$order->set_shipping_postcode('M1 1AA');
    \$order->set_shipping_country('GB');
    \$order->calculate_totals();
    \$order->update_meta_data('_mvtest_order', '1');
    \$order->update_meta_data('_magnavale_export_status', 'pending');
    \$order->save();

    \$box_calc = new MME_Box_Calculator();
    \$result = \$box_calc->calculate(\$order);

    \$has_ice = false;
    foreach (\$result['packaging'] as \$pkg) {
        if (\$pkg['code'] === '11DRYICE' || \$pkg['code'] === '11ICEPACK') {
            \$has_ice = true;
            break;
        }
    }

    echo \$has_ice ? 'HAS_ICE' : 'NO_ICE';
    \$order->delete(true);
" 2>/dev/null || true)

if [[ "$BUNDLE_FROZEN_CHECK" == "HAS_ICE" ]]; then
    log_pass "Frozen bundle has ice packs"
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
elif [[ "$BUNDLE_FROZEN_CHECK" == "NO_ICE" ]]; then
    log_fail "Frozen bundle missing ice packs"
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
elif [[ "$BUNDLE_FROZEN_CHECK" == "NO_BUNDLE" ]]; then
    skip_test "Frozen bundle ice pack test" "No frozen bundle product found"
else
    skip_test "Frozen bundle ice pack test" "Could not create bundle order: $BUNDLE_FROZEN_CHECK"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST 5: Ambient-only bundle — NO ice packs, NO dry ice
# Uses Sauce Selection Bundle (ID 15045) or Mayflower Mixes Bundle (ID 15141)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing ambient-only bundle has NO ice packs..."

BUNDLE_AMBIENT_CHECK=$(wp_cmd eval "
    \$order = wc_create_order(['status' => 'processing']);
    if (is_wp_error(\$order)) { echo 'CREATE_FAILED'; exit; }

    // Try ambient bundles: Sauce Selection (15045), Mayflower Mixes (15141)
    \$bundle_ids = [15045, 15141];
    \$added = false;
    foreach (\$bundle_ids as \$bid) {
        \$product = wc_get_product(\$bid);
        if (\$product) {
            \$line = new WC_Order_Item_Product();
            \$line->set_product(\$product);
            \$line->set_quantity(1);
            \$line->set_subtotal(\$product->get_price() ?: 10);
            \$line->set_total(\$product->get_price() ?: 10);
            \$order->add_item(\$line);
            \$added = true;
            break;
        }
    }

    if (!\$added) { echo 'NO_BUNDLE'; \$order->delete(true); exit; }

    \$order->set_billing_first_name('Test');
    \$order->set_billing_last_name('AmbientBundle');
    \$order->set_billing_address_1('1 Test St');
    \$order->set_billing_city('Manchester');
    \$order->set_billing_postcode('M1 1AA');
    \$order->set_billing_phone('07700900000');
    \$order->set_billing_email('test@example.com');
    \$order->set_shipping_first_name('Test');
    \$order->set_shipping_last_name('AmbientBundle');
    \$order->set_shipping_address_1('1 Test St');
    \$order->set_shipping_city('Manchester');
    \$order->set_shipping_postcode('M1 1AA');
    \$order->set_shipping_country('GB');
    \$order->calculate_totals();
    \$order->update_meta_data('_mvtest_order', '1');
    \$order->update_meta_data('_magnavale_export_status', 'pending');
    \$order->save();

    \$box_calc = new MME_Box_Calculator();
    \$result = \$box_calc->calculate(\$order);

    \$has_ice = false;
    foreach (\$result['packaging'] as \$pkg) {
        if (\$pkg['code'] === '11DRYICE' || \$pkg['code'] === '11ICEPACK') {
            \$has_ice = true;
            break;
        }
    }

    echo \$has_ice ? 'HAS_ICE' : 'NO_ICE';
    \$order->delete(true);
" 2>/dev/null || true)

if [[ "$BUNDLE_AMBIENT_CHECK" == "NO_ICE" ]]; then
    log_pass "Ambient-only bundle has NO ice packs (correct)"
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
elif [[ "$BUNDLE_AMBIENT_CHECK" == "HAS_ICE" ]]; then
    log_fail "Ambient-only bundle incorrectly has ice packs"
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
elif [[ "$BUNDLE_AMBIENT_CHECK" == "NO_BUNDLE" ]]; then
    skip_test "Ambient bundle ice pack test" "No ambient bundle product found"
else
    skip_test "Ambient bundle ice pack test" "Could not create bundle order: $BUNDLE_AMBIENT_CHECK"
fi

end_suite
