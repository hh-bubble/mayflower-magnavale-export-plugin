#!/bin/bash
# =============================================================================
# Validate No CSV Injection
# Scans both CSV files for any cell value that starts with dangerous
# characters: = + - @ | (tab)
# These could trigger formula execution when opened in Excel.
# =============================================================================

PLUGIN_DIR="${1:-$(dirname "$(dirname "$(realpath "$0")")")}"
ARCHIVE_DIR="${PLUGIN_DIR}/archives"

echo "=== Validate No CSV Injection ==="

ORDER_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_ORDERS_*.csv 2>/dev/null | head -1)
PACKING_CSV=$(ls -t "${ARCHIVE_DIR}"/KING01_PACKING_*.csv 2>/dev/null | head -1)

PASS=0
FAIL=0

check_file() {
    local FILE="$1"
    local LABEL="$2"
    local ISSUES=0

    if [ -z "$FILE" ]; then
        echo "  SKIP: No ${LABEL} CSV found"
        return
    fi

    echo ""
    echo "--- Checking ${LABEL}: $(basename "$FILE") ---"

    # Read each field from each row and check for dangerous starting characters
    LINE_NUM=0
    while IFS= read -r LINE; do
        ((LINE_NUM++))
        # Remove trailing CR if present
        LINE=$(echo "$LINE" | tr -d '\r')

        # Split by comma (basic — doesn't handle quoted commas perfectly)
        FIELD_NUM=0
        IFS=',' read -ra FIELDS <<< "$LINE"
        for FIELD in "${FIELDS[@]}"; do
            ((FIELD_NUM++))
            # Strip surrounding quotes
            CLEAN=$(echo "$FIELD" | sed 's/^"//; s/"$//')

            # Check if field starts with a dangerous character
            case "${CLEAN:0:1}" in
                "="|"+"|"-"|"@"|"|")
                    # Exceptions: negative numbers are OK, DPD service code 1^12 is OK
                    # Check if it's a number (e.g., -2, +3)
                    if echo "$CLEAN" | grep -qE '^[+-]?[0-9]+\.?[0-9]*$'; then
                        continue
                    fi
                    echo "  FAIL: Line ${LINE_NUM}, field ${FIELD_NUM}: starts with '${CLEAN:0:1}' => ${CLEAN:0:50}"
                    ((ISSUES++))
                    ((FAIL++))
                    ;;
            esac
        done
    done < "$FILE"

    if [ "$ISSUES" -eq 0 ]; then
        echo "  PASS: No CSV injection patterns found"
        ((PASS++))
    else
        echo "  Found ${ISSUES} potential injection issues"
    fi
}

check_file "$ORDER_CSV" "Order"
check_file "$PACKING_CSV" "Packing"

echo ""
echo "=== Results: ${PASS} passed, ${FAIL} failed ==="

if [ "$FAIL" -gt 0 ]; then
    echo ""
    echo "WARNING: CSV injection vulnerabilities detected!"
    echo "Fields starting with = + - @ | can execute formulas in Excel."
    echo "The plugin should prefix these with a tab or single quote."
fi

exit $FAIL
