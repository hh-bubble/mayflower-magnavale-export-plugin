#!/bin/bash
# =============================================================================
# Validate Packaging
# Checks the packing CSV for logical consistency:
#   - Box types (5OSS/5OSL) are present
#   - Insert counts match box counts (1 top + 1 sides per box)
#   - Ice pack counts are present
#   - No food products are missing
# =============================================================================

PLUGIN_DIR="${1:-$(dirname "$(dirname "$(realpath "$0")")")}"
ARCHIVE_DIR="${PLUGIN_DIR}/archives"

echo "=== Validate Packaging ==="

PACKING_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_PACKING_*.csv 2>/dev/null | head -1)

PASS=0
FAIL=0
WARN=0

check() {
    if [ "$1" = "true" ]; then
        echo "  PASS: $2"
        ((PASS++))
    else
        echo "  FAIL: $2"
        ((FAIL++))
    fi
}

warn() {
    echo "  WARN: $1"
    ((WARN++))
}

if [ -z "$PACKING_CSV" ]; then
    echo "FAIL: No packing CSV found in ${ARCHIVE_DIR}"
    exit 1
fi

echo "Checking: ${PACKING_CSV}"
echo ""

# Extract SKU (col M, index 12) and quantity (col O, index 14) from each row
declare -A ITEMS

while IFS=',' read -ra FIELDS; do
    SKU=$(echo "${FIELDS[12]}" | tr -d '"' | tr -d ' ')
    QTY=$(echo "${FIELDS[14]}" | tr -d '"' | tr -d ' ' | tr -d '\r')
    if [ -n "$SKU" ]; then
        ITEMS["$SKU"]=$QTY
    fi
done < "$PACKING_CSV"

echo "--- Items found in packing CSV ---"
for SKU in "${!ITEMS[@]}"; do
    echo "  ${SKU}: ${ITEMS[$SKU]}"
done
echo ""

# --- Check box/insert consistency ---
echo "--- Box & Insert Checks ---"

LARGE_BOXES=${ITEMS["5OSL"]:-0}
LARGE_TOP=${ITEMS["5OSLI"]:-0}
LARGE_SIDES=${ITEMS["5OSLIS"]:-0}
SMALL_BOXES=${ITEMS["5OSS"]:-0}
SMALL_TOP=${ITEMS["5OSSI"]:-0}
SMALL_SIDES=${ITEMS["5OSSIS"]:-0}

TOTAL_BOXES=$((LARGE_BOXES + SMALL_BOXES))
check "[ $TOTAL_BOXES -gt 0 ]" "At least one box present (${TOTAL_BOXES} total)"

if [ "$LARGE_BOXES" -gt 0 ]; then
    check "[ $LARGE_TOP -eq $LARGE_BOXES ]" "Large insert tops match large boxes (${LARGE_TOP} tops, ${LARGE_BOXES} boxes)"
    check "[ $LARGE_SIDES -eq $LARGE_BOXES ]" "Large insert sides match large boxes (${LARGE_SIDES} sides, ${LARGE_BOXES} boxes)"
fi

if [ "$SMALL_BOXES" -gt 0 ]; then
    check "[ $SMALL_TOP -eq $SMALL_BOXES ]" "Small insert tops match small boxes (${SMALL_TOP} tops, ${SMALL_BOXES} boxes)"
    check "[ $SMALL_SIDES -eq $SMALL_BOXES ]" "Small insert sides match small boxes (${SMALL_SIDES} sides, ${SMALL_BOXES} boxes)"
fi

# --- Check ice packs ---
echo ""
echo "--- Ice Pack Checks ---"

DRY_ICE=${ITEMS["DRYICE1KG"]:-0}
REGULAR_ICE=${ITEMS["ICEPACK"]:-0}

if [ "$DRY_ICE" -gt 0 ] || [ "$REGULAR_ICE" -gt 0 ]; then
    echo "  PASS: Ice packs present (dry: ${DRY_ICE}, regular: ${REGULAR_ICE})"
    ((PASS++))
else
    warn "No ice packs found — check if all orders were ambient-only"
fi

# --- Check food products exist ---
echo ""
echo "--- Food Product Checks ---"

FOOD_COUNT=0
for SKU in "${!ITEMS[@]}"; do
    # Skip packaging SKUs
    case "$SKU" in
        5OSL|5OSLI|5OSLIS|5OSS|5OSSI|5OSSIS|DRYICE1KG|ICEPACK) continue ;;
    esac
    ((FOOD_COUNT++))
done

check "[ $FOOD_COUNT -gt 0 ]" "Food products found in packing list (${FOOD_COUNT} distinct SKUs)"

echo ""
echo "=== Results: ${PASS} passed, ${FAIL} failed, ${WARN} warnings ==="
exit $FAIL
