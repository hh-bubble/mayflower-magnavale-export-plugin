#!/bin/bash
# =============================================================================
# Validate CSV Format
# Checks the most recent order and packing CSVs for correct structure:
#   - Order CSV: exactly 19 columns per row
#   - Packing CSV: exactly 15 columns per row
#   - No header row
#   - No blank rows
#   - Consistent line endings
# =============================================================================

PLUGIN_DIR="${1:-$(dirname "$(dirname "$(realpath "$0")")")}"
ARCHIVE_DIR="${PLUGIN_DIR}/archives"

echo "=== Validate CSV Format ==="
echo "Archive dir: ${ARCHIVE_DIR}"

if [ ! -d "$ARCHIVE_DIR" ]; then
    echo "FAIL: Archive directory not found at ${ARCHIVE_DIR}"
    exit 1
fi

# Find the most recent CSV files
ORDER_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_ORDERS_*.csv 2>/dev/null | head -1)
PACKING_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_PACKING_*.csv 2>/dev/null | head -1)

PASS=0
FAIL=0

check() {
    if [ "$1" = "true" ]; then
        echo "  PASS: $2"
        ((PASS++))
    else
        echo "  FAIL: $2"
        ((FAIL++))
    fi
}

# --- Order CSV checks ---
echo ""
echo "--- Order CSV: ${ORDER_CSV:-NOT FOUND} ---"

if [ -z "$ORDER_CSV" ]; then
    echo "  FAIL: No order CSV found in archives"
    ((FAIL++))
else
    # Check file is not empty
    ORDER_LINES=$(wc -l < "$ORDER_CSV" | tr -d ' ')
    check "[ $ORDER_LINES -gt 0 ]" "File is not empty (${ORDER_LINES} lines)"

    # Check column count (19 columns = 18 commas per line)
    # Count commas on each line, allowing for quoted fields
    BAD_COLS=$(awk -F',' '{if(NF != 19) print NR": "NF" columns"}' "$ORDER_CSV")
    if [ -z "$BAD_COLS" ]; then
        check "true" "All rows have exactly 19 columns"
    else
        check "false" "Some rows have wrong column count:"
        echo "$BAD_COLS" | head -5
    fi

    # Check no blank lines
    BLANK_LINES=$(grep -c '^[[:space:]]*$' "$ORDER_CSV" 2>/dev/null || echo "0")
    check "[ $BLANK_LINES -eq 0 ]" "No blank lines (found ${BLANK_LINES})"

    # Check first row is data, not a header (should start with KING01)
    FIRST_FIELD=$(head -1 "$ORDER_CSV" | cut -d',' -f1 | tr -d '"')
    check "[ \"$FIRST_FIELD\" = 'KING01' ]" "No header row (first field: ${FIRST_FIELD})"

    # Check line endings (should be CRLF for Windows compatibility)
    if file "$ORDER_CSV" | grep -q "CRLF"; then
        check "true" "Line endings are CRLF (Windows-style)"
    else
        check "false" "Line endings are NOT CRLF (expected Windows-style \\r\\n)"
    fi
fi

# --- Packing CSV checks ---
echo ""
echo "--- Packing CSV: ${PACKING_CSV:-NOT FOUND} ---"

if [ -z "$PACKING_CSV" ]; then
    echo "  FAIL: No packing CSV found in archives"
    ((FAIL++))
else
    PACKING_LINES=$(wc -l < "$PACKING_CSV" | tr -d ' ')
    check "[ $PACKING_LINES -gt 0 ]" "File is not empty (${PACKING_LINES} lines)"

    BAD_COLS=$(awk -F',' '{if(NF != 15) print NR": "NF" columns"}' "$PACKING_CSV")
    if [ -z "$BAD_COLS" ]; then
        check "true" "All rows have exactly 15 columns"
    else
        check "false" "Some rows have wrong column count:"
        echo "$BAD_COLS" | head -5
    fi

    BLANK_LINES=$(grep -c '^[[:space:]]*$' "$PACKING_CSV" 2>/dev/null || echo "0")
    check "[ $BLANK_LINES -eq 0 ]" "No blank lines (found ${BLANK_LINES})"

    FIRST_FIELD=$(head -1 "$PACKING_CSV" | cut -d',' -f1 | tr -d '"')
    check "[ \"$FIRST_FIELD\" = 'KING01' ]" "No header row (first field: ${FIRST_FIELD})"
fi

# --- Summary ---
echo ""
echo "=== Results: ${PASS} passed, ${FAIL} failed ==="
exit $FAIL
