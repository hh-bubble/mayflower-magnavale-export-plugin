#!/usr/bin/env bash
# ===========================================================================
# Mayflower Magnavale Export — Master Test Runner
# Runs all test suites in order and produces a summary report
# ===========================================================================
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS_DIR="${SCRIPT_DIR}"
LOG_DIR="${SCRIPT_DIR}/logs"
RESULTS_DIR="${SCRIPT_DIR}/results"
mkdir -p "$LOG_DIR" "$RESULTS_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
MASTER_LOG="${LOG_DIR}/master_${TIMESTAMP}.log"

# ── Colours ────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

echo "" | tee -a "$MASTER_LOG"
echo -e "${BOLD}╔══════════════════════════════════════════════════════════════╗${NC}" | tee -a "$MASTER_LOG"
echo -e "${BOLD}║     MAYFLOWER MAGNAVALE EXPORT — TEST SUITE                 ║${NC}" | tee -a "$MASTER_LOG"
echo -e "${BOLD}║     $(date)                              ║${NC}" | tee -a "$MASTER_LOG"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════════╝${NC}" | tee -a "$MASTER_LOG"
echo "" | tee -a "$MASTER_LOG"

# ── Environment info ───────────────────────────────────────────────────────
echo -e "${CYAN}Environment:${NC}" | tee -a "$MASTER_LOG"
echo "  WP_PATH:    ${WP_PATH:-.}" | tee -a "$MASTER_LOG"
echo "  FTP_HOST:   ${FTP_HOST:-s460.sureserver.com}" | tee -a "$MASTER_LOG"
echo "  SKIP_FTP:   ${SKIP_FTP:-0}" | tee -a "$MASTER_LOG"
echo "  SKIP_CLEANUP: ${SKIP_CLEANUP:-0}" | tee -a "$MASTER_LOG"
echo "" | tee -a "$MASTER_LOG"

# ── Pre-flight checks ─────────────────────────────────────────────────────
if ! command -v wp &> /dev/null; then
    echo -e "${RED}FATAL: WP-CLI not found. Install it first.${NC}" | tee -a "$MASTER_LOG"
    exit 1
fi

WP_PATH="${WP_PATH:-.}"
if ! wp core is-installed --path="$WP_PATH" --allow-root 2>/dev/null; then
    echo -e "${RED}FATAL: WordPress not found at $WP_PATH${NC}" | tee -a "$MASTER_LOG"
    echo "  Set WP_PATH=/path/to/wordpress and try again" | tee -a "$MASTER_LOG"
    exit 1
fi

echo -e "${GREEN}✓ WordPress found${NC}" | tee -a "$MASTER_LOG"
echo -e "${GREEN}✓ WP-CLI available${NC}" | tee -a "$MASTER_LOG"

# Check plugin
if wp plugin is-active mayflower-magnavale-export --path="$WP_PATH" --allow-root 2>/dev/null; then
    echo -e "${GREEN}✓ Plugin active${NC}" | tee -a "$MASTER_LOG"
else
    echo -e "${YELLOW}⚠ Plugin may not be active — some tests will skip${NC}" | tee -a "$MASTER_LOG"
fi
echo "" | tee -a "$MASTER_LOG"

# ── Run test suites ────────────────────────────────────────────────────────
TOTAL_SUITES=0
PASSED_SUITES=0
FAILED_SUITES=0
FAILED_NAMES=()

for test_file in "$TESTS_DIR"/[0-9]*.sh; do
    [[ -f "$test_file" ]] || continue
    
    suite_name=$(basename "$test_file" .sh)
    TOTAL_SUITES=$((TOTAL_SUITES + 1))
    
    echo -e "${BOLD}▶ Running: ${suite_name}${NC}" | tee -a "$MASTER_LOG"
    
    set +e
    bash "$test_file" 2>&1 | tee -a "$MASTER_LOG"
    exit_code=${PIPESTATUS[0]}
    set -e
    
    if [[ $exit_code -eq 0 ]]; then
        PASSED_SUITES=$((PASSED_SUITES + 1))
        echo -e "  ${GREEN}✓ ${suite_name} PASSED${NC}" | tee -a "$MASTER_LOG"
    else
        FAILED_SUITES=$((FAILED_SUITES + 1))
        FAILED_NAMES+=("$suite_name")
        echo -e "  ${RED}✗ ${suite_name} FAILED${NC}" | tee -a "$MASTER_LOG"
    fi
    echo "" | tee -a "$MASTER_LOG"
done

# ── Summary ────────────────────────────────────────────────────────────────
echo "" | tee -a "$MASTER_LOG"
echo -e "${BOLD}╔══════════════════════════════════════════════════════════════╗${NC}" | tee -a "$MASTER_LOG"
echo -e "${BOLD}║                    FINAL SUMMARY                            ║${NC}" | tee -a "$MASTER_LOG"
echo -e "${BOLD}╚══════════════════════════════════════════════════════════════╝${NC}" | tee -a "$MASTER_LOG"
echo "" | tee -a "$MASTER_LOG"
echo -e "  Test suites run:    ${TOTAL_SUITES}" | tee -a "$MASTER_LOG"
echo -e "  Suites passed:      ${GREEN}${PASSED_SUITES}${NC}" | tee -a "$MASTER_LOG"
echo -e "  Suites failed:      ${RED}${FAILED_SUITES}${NC}" | tee -a "$MASTER_LOG"
echo "" | tee -a "$MASTER_LOG"

if [[ $FAILED_SUITES -gt 0 ]]; then
    echo -e "${RED}Failed suites:${NC}" | tee -a "$MASTER_LOG"
    for name in "${FAILED_NAMES[@]}"; do
        echo -e "  ${RED}✗ ${name}${NC}" | tee -a "$MASTER_LOG"
    done
    echo "" | tee -a "$MASTER_LOG"
    echo -e "${BOLD}Log file: ${MASTER_LOG}${NC}" | tee -a "$MASTER_LOG"
    echo -e "${BOLD}Results:  ${RESULTS_DIR}/${NC}" | tee -a "$MASTER_LOG"
    exit 1
else
    echo -e "${GREEN}${BOLD}ALL TESTS PASSED ✓${NC}" | tee -a "$MASTER_LOG"
    echo "" | tee -a "$MASTER_LOG"
    exit 0
fi
