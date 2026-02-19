#!/usr/bin/env bash
# ===========================================================================
# Mayflower Test Framework — Shared Helpers
# ===========================================================================
set -euo pipefail

# ── Colours ────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No colour

# ── Counters ───────────────────────────────────────────────────────────────
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_SKIPPED=0
CURRENT_SUITE=""

# ── Directories ────────────────────────────────────────────────────────────
# Use _FW_DIR internally to avoid clobbering the caller's SCRIPT_DIR
_FW_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SUITE_DIR="$(dirname "$_FW_DIR")"
LOG_DIR="${SUITE_DIR}/logs"
RESULTS_DIR="${SUITE_DIR}/results"
mkdir -p "$LOG_DIR" "$RESULTS_DIR"

# ── Timestamps ─────────────────────────────────────────────────────────────
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${LOG_DIR}/test_${TIMESTAMP}.log"

# ── Configuration ──────────────────────────────────────────────────────────
WP_PATH="${WP_PATH:-.}"
TEST_PREFIX="${TEST_PREFIX:-MVTEST}"
SKIP_FTP="${SKIP_FTP:-0}"
SKIP_CLEANUP="${SKIP_CLEANUP:-0}"
FTP_HOST="${FTP_HOST:-s460.sureserver.com}"
FTP_USER="${FTP_USER:-magnavale}"
FTP_PASS="${FTP_PASS:-}"

# ── Logging ────────────────────────────────────────────────────────────────
log() {
    local msg="[$(date '+%H:%M:%S')] $*"
    echo "$msg" >> "$LOG_FILE"
    echo -e "$msg"
}

log_info()  { log "${CYAN}[INFO]${NC}  $*"; }
log_pass()  { log "${GREEN}[PASS]${NC}  $*"; }
log_fail()  { log "${RED}[FAIL]${NC}  $*"; }
log_warn()  { log "${YELLOW}[WARN]${NC}  $*"; }
log_skip()  { log "${YELLOW}[SKIP]${NC}  $*"; }

# ── Suite Management ───────────────────────────────────────────────────────
begin_suite() {
    CURRENT_SUITE="$1"
    TESTS_RUN=0
    TESTS_PASSED=0
    TESTS_FAILED=0
    TESTS_SKIPPED=0
    echo ""
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}  TEST SUITE: ${CYAN}${CURRENT_SUITE}${NC}"
    echo -e "${BOLD}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

end_suite() {
    echo ""
    echo -e "${BOLD}── Results: ${CURRENT_SUITE} ──${NC}"
    echo -e "  Total:   ${TESTS_RUN}"
    echo -e "  Passed:  ${GREEN}${TESTS_PASSED}${NC}"
    echo -e "  Failed:  ${RED}${TESTS_FAILED}${NC}"
    echo -e "  Skipped: ${YELLOW}${TESTS_SKIPPED}${NC}"
    echo ""

    # Write result to file
    local result_file="${RESULTS_DIR}/${CURRENT_SUITE// /_}_${TIMESTAMP}.txt"
    {
        echo "Suite: ${CURRENT_SUITE}"
        echo "Date:  $(date)"
        echo "Total: ${TESTS_RUN} | Pass: ${TESTS_PASSED} | Fail: ${TESTS_FAILED} | Skip: ${TESTS_SKIPPED}"
    } > "$result_file"

    if [[ $TESTS_FAILED -gt 0 ]]; then
        return 1
    fi
    return 0
}

# ── Assertions ─────────────────────────────────────────────────────────────
assert_equals() {
    local description="$1"
    local expected="$2"
    local actual="$3"
    TESTS_RUN=$((TESTS_RUN + 1))

    if [[ "$expected" == "$actual" ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description"
        log_fail "  Expected: '$expected'"
        log_fail "  Actual:   '$actual'"
    fi
}

assert_not_empty() {
    local description="$1"
    local value="$2"
    TESTS_RUN=$((TESTS_RUN + 1))

    if [[ -n "$value" ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — value was empty"
    fi
}

assert_contains() {
    local description="$1"
    local haystack="$2"
    local needle="$3"
    TESTS_RUN=$((TESTS_RUN + 1))

    if echo "$haystack" | grep -qF "$needle"; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description"
        log_fail "  String did not contain: '$needle'"
    fi
}

assert_not_contains() {
    local description="$1"
    local haystack="$2"
    local needle="$3"
    TESTS_RUN=$((TESTS_RUN + 1))

    if echo "$haystack" | grep -qF "$needle"; then
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description"
        log_fail "  String should NOT contain: '$needle'"
    else
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    fi
}

assert_file_exists() {
    local description="$1"
    local filepath="$2"
    TESTS_RUN=$((TESTS_RUN + 1))

    if [[ -f "$filepath" ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — file not found: $filepath"
    fi
}

assert_file_not_exists() {
    local description="$1"
    local filepath="$2"
    TESTS_RUN=$((TESTS_RUN + 1))

    if [[ ! -f "$filepath" ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — file unexpectedly exists: $filepath"
    fi
}

assert_true() {
    local description="$1"
    local condition="$2"
    TESTS_RUN=$((TESTS_RUN + 1))

    if eval "$condition"; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description"
    fi
}

assert_exit_code() {
    local description="$1"
    local expected_code="$2"
    shift 2
    TESTS_RUN=$((TESTS_RUN + 1))

    set +e
    "$@" > /dev/null 2>&1
    local actual_code=$?
    set -e

    if [[ $actual_code -eq $expected_code ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — expected exit $expected_code, got $actual_code"
    fi
}

assert_csv_columns() {
    local description="$1"
    local filepath="$2"
    local expected_count="$3"
    TESTS_RUN=$((TESTS_RUN + 1))

    if [[ ! -f "$filepath" ]]; then
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — file not found: $filepath"
        return
    fi

    local header_line
    header_line=$(head -1 "$filepath")
    # Count commas + 1 = column count
    local actual_count
    actual_count=$(echo "$header_line" | awk -F',' '{print NF}')

    if [[ "$actual_count" -eq "$expected_count" ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description (${actual_count} columns)"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — expected $expected_count columns, got $actual_count"
    fi
}

assert_csv_row_count() {
    local description="$1"
    local filepath="$2"
    local min_rows="$3"
    TESTS_RUN=$((TESTS_RUN + 1))

    if [[ ! -f "$filepath" ]]; then
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — file not found"
        return
    fi

    # Subtract 1 for header
    local actual_rows
    actual_rows=$(( $(wc -l < "$filepath") - 1 ))

    if [[ $actual_rows -ge $min_rows ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
        log_pass "$description (${actual_rows} data rows)"
    else
        TESTS_FAILED=$((TESTS_FAILED + 1))
        log_fail "$description — expected >= $min_rows rows, got $actual_rows"
    fi
}

skip_test() {
    local description="$1"
    local reason="${2:-No reason given}"
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_SKIPPED=$((TESTS_SKIPPED + 1))
    log_skip "$description — $reason"
}

# ── Utilities ──────────────────────────────────────────────────────────────
require_wp_cli() {
    if ! command -v wp &> /dev/null; then
        echo -e "${RED}ERROR: WP-CLI is not installed or not in PATH${NC}"
        exit 1
    fi
}

require_plugin_active() {
    local status
    status=$(wp plugin is-active mayflower-magnavale-export --path="$WP_PATH" 2>&1 && echo "active" || echo "inactive")
    if [[ "$status" != "active" ]]; then
        echo -e "${RED}ERROR: mayflower-magnavale-export plugin is not active${NC}"
        exit 1
    fi
}

wp_cmd() {
    wp "$@" --path="$WP_PATH" --allow-root 2>/dev/null
}
