#!/bin/bash
# =============================================================================
# Validate Delivery Dates
# Checks that delivery dates in the order CSV (column L) are valid working
# days — not weekends. Format expected: DD/MM/YYYY
# =============================================================================

PLUGIN_DIR="${1:-$(dirname "$(dirname "$(realpath "$0")")")}"
ARCHIVE_DIR="${PLUGIN_DIR}/archives"

echo "=== Validate Delivery Dates ==="

ORDER_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_ORDERS_*.csv 2>/dev/null | head -1)

PASS=0
FAIL=0

if [ -z "$ORDER_CSV" ]; then
    echo "FAIL: No order CSV found in ${ARCHIVE_DIR}"
    exit 1
fi

echo "Checking: ${ORDER_CSV}"
echo ""

DATES_SEEN=()

while IFS=',' read -ra FIELDS; do
    # Column L (index 11) is the delivery date
    DATE_RAW=$(echo "${FIELDS[11]}" | tr -d '"' | tr -d ' ')

    if [ -z "$DATE_RAW" ]; then
        echo "  FAIL: Empty delivery date on a row"
        ((FAIL++))
        continue
    fi

    # Parse DD/MM/YYYY
    DAY=$(echo "$DATE_RAW" | cut -d'/' -f1)
    MONTH=$(echo "$DATE_RAW" | cut -d'/' -f2)
    YEAR=$(echo "$DATE_RAW" | cut -d'/' -f3)

    # Validate format
    if ! echo "$DATE_RAW" | grep -qE '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'; then
        echo "  FAIL: Invalid date format: ${DATE_RAW} (expected DD/MM/YYYY)"
        ((FAIL++))
        continue
    fi

    # Convert to a date and check day of week (1=Mon, 7=Sun)
    # macOS date vs GNU date compatibility
    if date --version >/dev/null 2>&1; then
        # GNU date (Linux)
        DOW=$(date -d "${YEAR}-${MONTH}-${DAY}" +%u 2>/dev/null)
    else
        # BSD date (macOS)
        DOW=$(date -j -f "%Y-%m-%d" "${YEAR}-${MONTH}-${DAY}" +%u 2>/dev/null)
    fi

    if [ -z "$DOW" ]; then
        echo "  FAIL: Could not parse date: ${DATE_RAW}"
        ((FAIL++))
        continue
    fi

    # Check it's a weekday (delivery should be Wed, Thu, or Fri based on plugin logic)
    if [ "$DOW" -ge 6 ]; then
        echo "  FAIL: Delivery date ${DATE_RAW} falls on a weekend (day ${DOW})"
        ((FAIL++))
    else
        ((PASS++))
    fi

    # Track unique dates
    if [[ ! " ${DATES_SEEN[*]} " =~ " ${DATE_RAW} " ]]; then
        DATES_SEEN+=("$DATE_RAW")
    fi

done < "$ORDER_CSV"

echo ""
echo "Unique delivery dates found: ${DATES_SEEN[*]}"
echo ""
echo "=== Results: ${PASS} passed, ${FAIL} failed ==="
exit $FAIL
