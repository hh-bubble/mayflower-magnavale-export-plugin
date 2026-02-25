#!/bin/bash
# =============================================================================
# Validate SKUs
# Checks that every product SKU in the order and packing CSVs is a known
# Magnavale product code. Flags any unknown SKUs.
# =============================================================================

PLUGIN_DIR="${1:-$(dirname "$(dirname "$(realpath "$0")")")}"
ARCHIVE_DIR="${PLUGIN_DIR}/archives"

echo "=== Validate SKUs ==="

# All valid Magnavale product codes (food + packaging)
VALID_SKUS=(
    # Frozen products
    "12SMD" "12PD" "12CHARSIU" "12DSR" "EFR12227" "12PW"
    "CC12227" "CKP16300" "CSB12300" "SSCB12255" "CNC12330" "GNC12330"
    "CBBQR12350" "SNPR12280" "BBNT12400" "CMNT12400" "SCSNT12400"
    "SPCNT12400" "BR16200" "CBBN12400" "SFR12227"
    # Sauces
    "HS12400" "SCS12400" "CBBS12400" "CSSS12400" "CSTS12400"
    "SFS12400" "SZS12400" "CCCO12180"
    # Sauce pots
    "CS30227" "SS30227"
    # Sauce mixes (retail)
    "CSM12255A" "CSMH12255A" "SSGM12255A"
    # Sauce mixes (catering)
    "CSMA1454" "CSMAH1454" "SSGM1454"
    # Packaging materials
    "5OSL" "5OSLI" "5OSLIS" "5OSS" "5OSSI" "5OSSIS"
    "DRYICE1KG" "ICEPACK"
)

ORDER_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_ORDERS_*.csv 2>/dev/null | head -1)
PACKING_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_PACKING_*.csv 2>/dev/null | head -1)

PASS=0
FAIL=0

is_valid_sku() {
    local sku="$1"
    for valid in "${VALID_SKUS[@]}"; do
        if [ "$sku" = "$valid" ]; then
            return 0
        fi
    done
    return 1
}

# --- Check Order CSV (column 13 = field index 12, 0-based) ---
echo ""
echo "--- Order CSV SKUs ---"

if [ -z "$ORDER_CSV" ]; then
    echo "  SKIP: No order CSV found"
else
    UNKNOWN=""
    while IFS=',' read -ra FIELDS; do
        # Column M (index 12) is the product code
        SKU=$(echo "${FIELDS[12]}" | tr -d '"' | tr -d ' ')
        if [ -n "$SKU" ] && ! is_valid_sku "$SKU"; then
            UNKNOWN="${UNKNOWN}  UNKNOWN: ${SKU}\n"
            ((FAIL++))
        else
            ((PASS++))
        fi
    done < "$ORDER_CSV"

    if [ -z "$UNKNOWN" ]; then
        echo "  PASS: All SKUs in order CSV are valid Magnavale codes"
    else
        echo "  FAIL: Unknown SKUs found in order CSV:"
        echo -e "$UNKNOWN"
    fi
fi

# --- Check Packing CSV (column 13 = field index 12) ---
echo ""
echo "--- Packing CSV SKUs ---"

if [ -z "$PACKING_CSV" ]; then
    echo "  SKIP: No packing CSV found"
else
    UNKNOWN=""
    while IFS=',' read -ra FIELDS; do
        SKU=$(echo "${FIELDS[12]}" | tr -d '"' | tr -d ' ')
        if [ -n "$SKU" ] && ! is_valid_sku "$SKU"; then
            UNKNOWN="${UNKNOWN}  UNKNOWN: ${SKU}\n"
            ((FAIL++))
        else
            ((PASS++))
        fi
    done < "$PACKING_CSV"

    if [ -z "$UNKNOWN" ]; then
        echo "  PASS: All SKUs in packing CSV are valid Magnavale codes"
    else
        echo "  FAIL: Unknown SKUs found in packing CSV:"
        echo -e "$UNKNOWN"
    fi
fi

echo ""
echo "=== Results: ${PASS} valid, ${FAIL} unknown ==="
exit $FAIL
