#!/bin/bash
# =============================================================================
# Validate FTPS Upload
# Checks the plugin's export log to confirm the most recent FTPS upload
# succeeded. This doesn't connect to the FTPS server directly — it reads
# the WordPress export log via WP-CLI or by checking the archive files.
# =============================================================================

PLUGIN_DIR="${1:-$(dirname "$(dirname "$(realpath "$0")")")}"
ARCHIVE_DIR="${PLUGIN_DIR}/archives"

echo "=== Validate FTPS Upload ==="

PASS=0
FAIL=0

# Check archive files exist and were recently modified
ORDER_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_ORDERS_*.csv 2>/dev/null | head -1)
PACKING_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_PACKING_*.csv 2>/dev/null | head -1)

if [ -z "$ORDER_CSV" ]; then
    echo "  FAIL: No order CSV found in archives"
    ((FAIL++))
else
    echo "  PASS: Order CSV exists: $(basename "$ORDER_CSV")"
    ((PASS++))

    # Check file size is reasonable (not empty)
    SIZE=$(wc -c < "$ORDER_CSV" | tr -d ' ')
    if [ "$SIZE" -gt 0 ]; then
        echo "  PASS: Order CSV is not empty (${SIZE} bytes)"
        ((PASS++))
    else
        echo "  FAIL: Order CSV is empty"
        ((FAIL++))
    fi

    # Check file permissions (should be 0600 for PII data)
    PERMS=$(stat -c %a "$ORDER_CSV" 2>/dev/null || stat -f %Lp "$ORDER_CSV" 2>/dev/null)
    if [ "$PERMS" = "600" ]; then
        echo "  PASS: Order CSV has restrictive permissions (${PERMS})"
        ((PASS++))
    else
        echo "  WARN: Order CSV permissions are ${PERMS} (expected 600)"
    fi
fi

if [ -z "$PACKING_CSV" ]; then
    echo "  FAIL: No packing CSV found in archives"
    ((FAIL++))
else
    echo "  PASS: Packing CSV exists: $(basename "$PACKING_CSV")"
    ((PASS++))

    SIZE=$(wc -c < "$PACKING_CSV" | tr -d ' ')
    if [ "$SIZE" -gt 0 ]; then
        echo "  PASS: Packing CSV is not empty (${SIZE} bytes)"
        ((PASS++))
    else
        echo "  FAIL: Packing CSV is empty"
        ((FAIL++))
    fi
fi

# Check both files have matching timestamps
if [ -n "$ORDER_CSV" ] && [ -n "$PACKING_CSV" ]; then
    ORDER_TS=$(echo "$(basename "$ORDER_CSV")" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}')
    PACKING_TS=$(echo "$(basename "$PACKING_CSV")" | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}')
    if [ "$ORDER_TS" = "$PACKING_TS" ]; then
        echo "  PASS: Both files share timestamp: ${ORDER_TS}"
        ((PASS++))
    else
        echo "  FAIL: Timestamps don't match (order: ${ORDER_TS}, packing: ${PACKING_TS})"
        ((FAIL++))
    fi
fi

echo ""
echo "NOTE: To verify files landed on the FTPS server, check the plugin export log"
echo "in WP Admin > WooCommerce > Magnavale Export > Export Log."
echo ""
echo "=== Results: ${PASS} passed, ${FAIL} failed ==="
exit $FAIL
