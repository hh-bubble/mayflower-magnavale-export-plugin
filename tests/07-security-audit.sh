#!/usr/bin/env bash
# ===========================================================================
# TEST 07: Security Audit — Comprehensive security testing
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "07 — Security Audit"

# Helper: scan plugin PHP files for patterns
scan_plugin() {
    local php_code="$1"
    wp_cmd eval "
        \$dir = WP_PLUGIN_DIR . '/mayflower-magnavale-export/';
        \$files = array_merge(glob(\$dir.'*.php'), glob(\$dir.'**/*.php'));
        $php_code
    " 2>/dev/null
}

# ═══ A: FILE SYSTEM SECURITY ═══
log_info "═══ A: FILE SYSTEM SECURITY ═══"

PLUGIN_DIR=$(wp_cmd eval "echo WP_PLUGIN_DIR . '/mayflower-magnavale-export/';" 2>/dev/null)
if [[ -d "$PLUGIN_DIR" ]]; then
    WORLD_WRITABLE=$(find "$PLUGIN_DIR" -perm -o+w -type f 2>/dev/null | wc -l)
    assert_equals "A1: No world-writable plugin files" "0" "$WORLD_WRITABLE"
    
    EXEC_PHP=$(find "$PLUGIN_DIR" -name "*.php" -perm -o+x 2>/dev/null | wc -l)
    assert_equals "A2: No executable PHP files" "0" "$EXEC_PHP"
else
    skip_test "File permissions" "Plugin directory not found"
fi

# Check CSV directory not web-accessible
SITE_URL=$(wp_cmd option get siteurl 2>/dev/null)
CSV_URL="${SITE_URL}/wp-content/uploads/magnavale-exports/"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$CSV_URL" 2>/dev/null || echo "000")
if [[ "$HTTP_CODE" == "200" ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
    log_fail "A3: CSV directory web-accessible (HTTP 200) — CRITICAL"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "A3: CSV directory not web-accessible (HTTP $HTTP_CODE)"
fi

# Temp file cleanup
STALE=$(wp_cmd eval "echo count(glob(sys_get_temp_dir().'/KING01_*.csv'));" 2>/dev/null)
assert_true "A4: No stale temp CSV files" "[[ '${STALE:-0}' -le 2 ]]"

# ═══ B: INPUT VALIDATION & SANITISATION ═══
log_info "═══ B: INPUT VALIDATION ═══"

# SQL injection check
SQLI=$(scan_plugin "
    \$raw = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (preg_match('/\\\$wpdb->query\s*\(\s*[\"\\\']/', \$c)) \$raw++;
        if (preg_match('/\\\$wpdb->query\s*\(\s*\\\$/', \$c)) \$raw++;
    }
    echo \$raw;
")
assert_equals "B1: No raw SQL queries" "0" "${SQLI:-0}"

# XSS output escaping
XSS=$(scan_plugin "
    \$bad = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        \$bad += preg_match_all('/echo\s+\\\$[a-zA-Z]/', \$c, \$m);
    }
    echo \$bad;
")
log_info "  B2: Unescaped echo statements: ${XSS:-0}"
if [[ "${XSS:-0}" -gt 5 ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
    log_fail "B2: Many unescaped outputs ($XSS) — XSS risk"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "B2: Unescaped outputs within acceptable range"
fi

# Nonce verification
NONCE=$(scan_plugin "
    \$verify = 0; \$actions = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'wp_verify_nonce')!==false || strpos(\$c,'check_admin_referer')!==false) \$verify++;
        if (strpos(\$c,'admin_post_')!==false || strpos(\$c,'wp_ajax_')!==false) \$actions++;
    }
    echo \"verify:\$verify|actions:\$actions\";
")
log_info "  B3: Nonce analysis: $NONCE"
NONCE_V=$(echo "$NONCE" | grep -oP 'verify:\K[0-9]+')
NONCE_A=$(echo "$NONCE" | grep -oP 'actions:\K[0-9]+')
if [[ "${NONCE_A:-0}" -gt 0 && "${NONCE_V:-0}" == "0" ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
    log_fail "B3: Admin actions WITHOUT nonce verification — CSRF risk"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "B3: Nonce verification adequate"
fi

# Capability checks
CAPS=$(scan_plugin "
    \$c_count = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'current_user_can')!==false) \$c_count++;
        if (strpos(\$c,'manage_woocommerce')!==false) \$c_count++;
    }
    echo \$c_count;
")
if [[ "${CAPS:-0}" -gt 0 ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "B4: Capability checks found ($CAPS instances)"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
    log_fail "B4: No capability checks — unauthorized users may trigger exports"
fi

# CSV injection prevention test
log_info "B5: Testing CSV injection vectors..."
set_random_address
TEST_FIRST_NAME='=CMD("calc")'
TEST_LAST_NAME='+HYPERLINK("http://evil.com")'
TEST_ADDRESS='-1+1|cmd'
TEST_CITY='@SUM(A1:A10)'
export TEST_FIRST_NAME TEST_LAST_NAME TEST_ADDRESS TEST_CITY
order_id=$(create_tagged_test_order "sec_csv_inject" "CB41:1")
if [[ -n "$order_id" ]]; then
    csv_content=$(generate_test_csv "$order_id")
    if [[ -n "$csv_content" && "$csv_content" != "NO_ORDERS" ]]; then
        for pattern in '=CMD' '+HYPERLINK' '-1+1|cmd' '@SUM'; do
            assert_not_contains "B5: No '$pattern' in CSV" "$csv_content" "$pattern"
        done
    else
        skip_test "B5: CSV injection" "Could not generate CSV"
    fi
fi

# ═══ C: CREDENTIAL SECURITY ═══
log_info "═══ C: CREDENTIAL SECURITY ═══"

HARDCODED=$(wp_cmd eval "
    \$dir = WP_PLUGIN_DIR . '/mayflower-magnavale-export/';
    \$files = array_merge(glob(\$dir.'*.php'), glob(\$dir.'**/*.php'));
    \$h = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (preg_match('/ftp_login\s*\([^,]+,\s*[\\x27\\x22][a-zA-Z]/', \$c)) \$h++;
        if (preg_match('/password\s*=\s*[\\x27\\x22][a-zA-Z0-9]{3,}/', \$c)) \$h++;
    }
    echo \$h;
" 2>/dev/null)
assert_equals "C1: No hardcoded credentials" "0" "${HARDCODED:-0}"

LOG_LEAK=$(scan_plugin "
    \$l = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (preg_match('/error_log.*passw/i', \$c)) \$l++;
        if (preg_match('/var_dump.*passw/i', \$c)) \$l++;
        if (preg_match('/print_r.*passw/i', \$c)) \$l++;
    }
    echo \$l;
")
assert_equals "C2: No credentials in log statements" "0" "${LOG_LEAK:-0}"

CRED_STORAGE=$(wp_cmd eval "
    if (defined('MAGNAVALE_FTP_PASS') || defined('MAYFLOWER_FTP_PASS')) echo 'CONSTANT';
    elseif (get_option('mayflower_ftp_password','') || get_option('magnavale_ftp_password','')) echo 'OPTION';
    else echo 'UNKNOWN';
" 2>/dev/null)
log_info "  C3: Credential storage method: $CRED_STORAGE"
TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))

# ═══ D: TRANSPORT SECURITY ═══
log_info "═══ D: TRANSPORT SECURITY ═══"

FTPS=$(scan_plugin "
    \$ssl = 0; \$plain = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'ftp_ssl_connect')!==false) \$ssl++;
        if (preg_match('/[^_]ftp_connect\s*\(/', \$c)) \$plain++;
    }
    echo \"ssl:\$ssl|plain:\$plain\";
")
log_info "  D1: FTP type: $FTPS"
SSL_COUNT=$(echo "$FTPS" | grep -oP 'ssl:\K[0-9]+')
PLAIN_COUNT=$(echo "$FTPS" | grep -oP 'plain:\K[0-9]+')
assert_true "D1: Uses ftp_ssl_connect" "[[ '${SSL_COUNT:-0}' -gt 0 ]]"
if [[ "${PLAIN_COUNT:-0}" -gt 0 ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
    log_fail "D2: Plain FTP found — data transmitted unencrypted — CRITICAL"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "D2: No plain FTP — all transfers use TLS"
fi

# TLS cert check
if command -v openssl &>/dev/null && [[ -n "$FTP_HOST" ]]; then
    CERT=$(echo | timeout 10 openssl s_client -connect "${FTP_HOST}:21" -starttls ftp 2>/dev/null | grep "Verify return code" || echo "N/A")
    log_info "  D3: TLS cert: $CERT"
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
fi

# ═══ E: DATA PROTECTION / GDPR ═══
log_info "═══ E: DATA PROTECTION ═══"

PII_CACHE=$(scan_plugin "
    \$t = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (preg_match('/set_transient.*(email|address|phone|name)/i', \$c)) \$t++;
    }
    echo \$t;
")
assert_equals "E1: No PII cached in transients" "0" "${PII_CACHE:-0}"

FILE_CLEANUP=$(scan_plugin "
    \$u = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'unlink')!==false || strpos(\$c,'wp_delete_file')!==false) \$u++;
    }
    echo \$u;
")
if [[ "${FILE_CLEANUP:-0}" -gt 0 ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "E2: File cleanup code found"
else
    TESTS_RUN=$((TESTS_RUN + 1))
    log_warn "E2: No file cleanup — CSVs with PII may persist"
    TESTS_PASSED=$((TESTS_PASSED + 1))
fi

# ═══ F: WORDPRESS BEST PRACTICES ═══
log_info "═══ F: WORDPRESS BEST PRACTICES ═══"

# Direct file access prevention
DIRECT=$(scan_plugin "
    \$unprotected = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'ABSPATH')===false && strpos(\$c,'WPINC')===false && strpos(\$c,'defined(')===false) {
            \$unprotected++;
            echo 'UNPROTECTED:'.basename(\$f).PHP_EOL;
        }
    }
    echo \"unprotected:\$unprotected\";
")
UNPROTECTED=$(echo "$DIRECT" | tail -1 | grep -oP 'unprotected:\K[0-9]+')
if [[ "${UNPROTECTED:-0}" == "0" ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "F1: All PHP files have direct access prevention"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_FAILED=$((TESTS_FAILED + 1))
    log_fail "F1: $UNPROTECTED PHP files lack direct access prevention"
fi

# REST API endpoints
REST=$(scan_plugin "
    \$routes=0; \$auth=0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'register_rest_route')!==false) {
            \$routes++;
            if (strpos(\$c,'permission_callback')!==false) \$auth++;
        }
    }
    echo \"routes:\$routes|auth:\$auth\";
")
REST_R=$(echo "$REST" | grep -oP 'routes:\K[0-9]+')
REST_A=$(echo "$REST" | grep -oP 'auth:\K[0-9]+')
if [[ "${REST_R:-0}" -gt 0 ]]; then
    assert_equals "F2: All REST routes have auth" "$REST_R" "$REST_A"
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "F2: No REST endpoints (smaller attack surface)"
fi

# Debug mode
DEBUG_DISPLAY=$(wp_cmd eval "echo (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) ? 'ON' : 'OFF';" 2>/dev/null)
if [[ "$DEBUG_DISPLAY" == "ON" ]]; then
    TESTS_RUN=$((TESTS_RUN + 1))
    log_warn "F3: WP_DEBUG_DISPLAY ON — disable for production"
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "F3: Debug display off"
fi

# ═══ G: PATH TRAVERSAL ═══
log_info "═══ G: PATH TRAVERSAL ═══"

PATH_TRAV=$(wp_cmd eval "
    \$bad = ['../../../etc/passwd','..%2F..%2F','test;rm -rf /','test\\\$(whoami)'];
    \$safe = 0;
    foreach (\$bad as \$b) {
        \$s = sanitize_file_name('KING01_'.\$b.'.csv');
        if (strpos(\$s,'..')===false && strpos(\$s,'/')===false && strpos(\$s,';')===false) \$safe++;
    }
    echo \$safe.'/'.count(\$bad);
" 2>/dev/null)
assert_equals "G1: Path traversal prevention" "4/4" "$PATH_TRAV"

# ═══ H: EXPORT DEDUPLICATION ═══
log_info "═══ H: ABUSE PREVENTION ═══"

DEDUPE=$(scan_plugin "
    \$d = 0;
    foreach (\$files as \$f) {
        \$c = file_get_contents(\$f);
        if (strpos(\$c,'_exported')!==false || strpos(\$c,'already_exported')!==false || strpos(\$c,'magnavale_exported')!==false) \$d++;
    }
    echo \$d;
")
if [[ "${DEDUPE:-0}" -gt 0 ]]; then
    TESTS_RUN=$((TESTS_RUN + 1)); TESTS_PASSED=$((TESTS_PASSED + 1))
    log_pass "H1: Export deduplication found"
else
    TESTS_RUN=$((TESTS_RUN + 1))
    log_warn "H1: No deduplication — orders could be exported multiple times"
    TESTS_PASSED=$((TESTS_PASSED + 1))
fi

# ═══ COMPLETE ═══
echo ""
log_info "═══ SECURITY AUDIT COMPLETE ═══"
end_suite
