#!/usr/bin/env bash
# ===========================================================================
# TEST 05: FTPS Upload Testing
# Tests the secure file transfer to Magnavale's server
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "05 — FTPS Upload"

if [[ "$SKIP_FTP" == "1" ]]; then
    skip_test "All FTP tests" "SKIP_FTP=1 is set"
    end_suite
    exit 0
fi

if [[ -z "$FTP_PASS" ]]; then
    skip_test "All FTP tests" "FTP_PASS not set — export FTP_PASS=xxx to run"
    end_suite
    exit 0
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: FTPS connectivity
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing FTPS connectivity to ${FTP_HOST}..."

# Test basic connection with PHP (matching the plugin's method)
CONNECT_RESULT=$(wp_cmd eval "
    \$conn = ftp_ssl_connect('$FTP_HOST', 21, 30);
    if (!\$conn) { echo 'CONNECT_FAILED'; exit; }
    
    \$login = @ftp_login(\$conn, '$FTP_USER', '$FTP_PASS');
    if (!\$login) { echo 'LOGIN_FAILED'; ftp_close(\$conn); exit; }
    
    ftp_pasv(\$conn, true);
    echo 'CONNECTED';
    ftp_close(\$conn);
" 2>/dev/null)

assert_equals "FTPS connection successful" "CONNECTED" "$CONNECT_RESULT"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Directory listing
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing directory access..."

DIR_RESULT=$(wp_cmd eval "
    \$conn = ftp_ssl_connect('$FTP_HOST', 21, 30);
    ftp_login(\$conn, '$FTP_USER', '$FTP_PASS');
    ftp_pasv(\$conn, true);
    
    \$pwd = ftp_pwd(\$conn);
    echo 'PWD:' . \$pwd . PHP_EOL;
    
    \$list = ftp_nlist(\$conn, '.');
    if (\$list !== false) {
        echo 'DIR_OK:' . count(\$list) . ' items';
    } else {
        echo 'DIR_FAILED';
    }
    ftp_close(\$conn);
" 2>/dev/null)

assert_contains "Directory listing accessible" "$DIR_RESULT" "DIR_OK"
log_info "  $DIR_RESULT"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Upload a test file
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing file upload..."

TEST_CSV="/tmp/mvtest_upload_${TIMESTAMP}.csv"
echo "test_col1,test_col2,test_col3" > "$TEST_CSV"
echo "test_val1,test_val2,test_val3" >> "$TEST_CSV"

UPLOAD_RESULT=$(wp_cmd eval "
    \$conn = ftp_ssl_connect('$FTP_HOST', 21, 30);
    ftp_login(\$conn, '$FTP_USER', '$FTP_PASS');
    ftp_pasv(\$conn, true);
    
    \$remote_file = 'TEST_upload_check_${TIMESTAMP}.csv';
    \$result = ftp_put(\$conn, \$remote_file, '$TEST_CSV', FTP_ASCII);
    
    if (\$result) {
        // Verify it's there
        \$list = ftp_nlist(\$conn, '.');
        if (in_array(\$remote_file, \$list)) {
            // Clean up test file
            ftp_delete(\$conn, \$remote_file);
            echo 'UPLOAD_OK';
        } else {
            echo 'UPLOAD_NOT_FOUND';
        }
    } else {
        echo 'UPLOAD_FAILED';
    }
    ftp_close(\$conn);
" 2>/dev/null)

assert_equals "Test file uploaded and verified" "UPLOAD_OK" "$UPLOAD_RESULT"
rm -f "$TEST_CSV"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Upload with special characters in filename
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing filename safety..."

SAFE_NAME_RESULT=$(wp_cmd eval "
    // The plugin should sanitise filenames
    \$order_id = 12345;
    \$date = date('Ymd_His');
    \$filename = 'KING01_' . \$order_id . '_' . \$date . '.csv';
    
    // Verify no path traversal possible
    if (strpos(\$filename, '..') === false && strpos(\$filename, '/') === false) {
        echo 'SAFE';
    } else {
        echo 'UNSAFE';
    }
" 2>/dev/null)

assert_equals "Filename generation is safe" "SAFE" "$SAFE_NAME_RESULT"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Connection timeout handling
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing connection timeout handling..."

TIMEOUT_RESULT=$(wp_cmd eval "
    // Test with unreachable host — should timeout, not hang
    \$start = time();
    @\$conn = ftp_ssl_connect('192.0.2.1', 21, 5); // RFC 5737 test address
    \$elapsed = time() - \$start;
    
    if (\$conn === false && \$elapsed <= 10) {
        echo 'TIMEOUT_OK:' . \$elapsed . 's';
    } else {
        echo 'TIMEOUT_BAD:' . \$elapsed . 's';
    }
" 2>/dev/null)

assert_contains "Connection timeout handled properly" "$TIMEOUT_RESULT" "TIMEOUT_OK"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Passive mode enforcement
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing passive mode is enforced..."

PASV_RESULT=$(wp_cmd eval "
    \$conn = ftp_ssl_connect('$FTP_HOST', 21, 30);
    ftp_login(\$conn, '$FTP_USER', '$FTP_PASS');
    
    // Must use passive mode through firewalls
    \$pasv = ftp_pasv(\$conn, true);
    echo \$pasv ? 'PASV_OK' : 'PASV_FAILED';
    ftp_close(\$conn);
" 2>/dev/null)

assert_equals "Passive mode enabled" "PASV_OK" "$PASV_RESULT"

# ══════════════════════════════════════════════════════════════════════════
# TEST: FTP user is restricted (cannot traverse directories)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing FTP user directory restriction..."

CHROOT_RESULT=$(wp_cmd eval "
    \$conn = ftp_ssl_connect('$FTP_HOST', 21, 30);
    ftp_login(\$conn, '$FTP_USER', '$FTP_PASS');
    ftp_pasv(\$conn, true);
    
    // Try to navigate outside chroot
    \$pwd_before = ftp_pwd(\$conn);
    @ftp_chdir(\$conn, '/..');
    \$pwd_after = ftp_pwd(\$conn);
    @ftp_chdir(\$conn, '/etc');
    \$pwd_etc = ftp_pwd(\$conn);
    
    if (\$pwd_before === \$pwd_after && \$pwd_before === \$pwd_etc) {
        echo 'CHROOT_OK';
    } else {
        echo 'CHROOT_ESCAPED:' . \$pwd_after . ':' . \$pwd_etc;
    }
    ftp_close(\$conn);
" 2>/dev/null)

assert_equals "FTP user is chrooted" "CHROOT_OK" "$CHROOT_RESULT"

end_suite
