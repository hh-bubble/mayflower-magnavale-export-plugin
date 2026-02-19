#!/usr/bin/env bash
# ===========================================================================
# TEST 08: Concurrency & Stress Testing
# Tests simultaneous order processing and system stability
# ===========================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/lib/test-framework.sh"
source "${SCRIPT_DIR}/lib/wp-helpers.sh"

require_wp_cli

begin_suite "08 — Concurrency & Stress"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Rapid sequential order creation
# ══════════════════════════════════════════════════════════════════════════
log_info "Creating 10 orders in rapid succession..."
RAPID_IDS=()
RAPID_ERRORS=0

for i in $(seq 1 10); do
    set_random_address
    oid=$(create_tagged_test_order "stress_rapid_$i" "CB41:$i")
    if [[ -n "$oid" ]]; then
        RAPID_IDS+=("$oid")
    else
        RAPID_ERRORS=$((RAPID_ERRORS + 1))
    fi
done

assert_equals "10 rapid orders created without errors" "0" "$RAPID_ERRORS"
log_info "  Created ${#RAPID_IDS[@]} orders: ${RAPID_IDS[*]}"

# ══════════════════════════════════════════════════════════════════════════
# TEST: Parallel CSV generation (background jobs)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing parallel CSV generation..."
PARALLEL_DIR="/tmp/mvtest_parallel_${TIMESTAMP}"
mkdir -p "$PARALLEL_DIR"

PIDS=()
for oid in "${RAPID_IDS[@]}"; do
    (
        wp_cmd eval "
            if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
                \$e = new Mayflower_Magnavale_CSV_Exporter();
                \$csv = \$e->generate_csv($oid);
                file_put_contents('${PARALLEL_DIR}/order_${oid}.csv', \$csv);
                echo 'OK';
            } else {
                echo 'SKIP';
            }
        " > "${PARALLEL_DIR}/result_${oid}.txt" 2>/dev/null
    ) &
    PIDS+=($!)
done

# Wait for all background jobs
PARALLEL_FAILS=0
for pid in "${PIDS[@]}"; do
    wait "$pid" || PARALLEL_FAILS=$((PARALLEL_FAILS + 1))
done

assert_equals "All parallel CSV jobs completed" "0" "$PARALLEL_FAILS"

# Verify CSV files are distinct (not jumbled)
DISTINCT=0
TOTAL_CSV=0
for csv_file in "${PARALLEL_DIR}"/order_*.csv; do
    [[ -f "$csv_file" ]] || continue
    TOTAL_CSV=$((TOTAL_CSV + 1))
    # Extract order ID from content
    oid_in_name=$(basename "$csv_file" .csv | sed 's/order_//')
    if grep -qF "$oid_in_name" "$csv_file" 2>/dev/null; then
        DISTINCT=$((DISTINCT + 1))
    fi
done

if [[ $TOTAL_CSV -gt 0 ]]; then
    assert_equals "All CSVs contain correct order data" "$TOTAL_CSV" "$DISTINCT"
else
    skip_test "Parallel CSV verification" "No CSVs generated (check export class)"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Large order stress (many line items)
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing large order (all products x3)..."
set_random_address
LARGE_ARGS=()
for sku in "${FOOD_SKUS[@]}"; do
    LARGE_ARGS+=("${sku}:3")
done

START_TIME=$(date +%s)
large_order=$(create_tagged_test_order "stress_large" "${LARGE_ARGS[@]}")
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

assert_not_empty "Large order created (${#FOOD_SKUS[@]} SKUs x3)" "$large_order"
log_info "  Large order creation took ${ELAPSED}s"
assert_true "Large order created within 60s" "[[ $ELAPSED -lt 60 ]]"

# CSV generation timing
if [[ -n "$large_order" ]]; then
    START_TIME=$(date +%s)
    wp_cmd eval "
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            \$csv = \$e->generate_csv($large_order);
            echo strlen(\$csv);
        } else { echo 'SKIP'; }
    " 2>/dev/null
    END_TIME=$(date +%s)
    CSV_ELAPSED=$((END_TIME - START_TIME))
    log_info "  CSV generation for large order took ${CSV_ELAPSED}s"
    assert_true "CSV generation under 30s" "[[ $CSV_ELAPSED -lt 30 ]]"
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: Memory usage check
# ══════════════════════════════════════════════════════════════════════════
log_info "Testing memory usage during export..."
if [[ -n "$large_order" ]]; then
    MEM_RESULT=$(wp_cmd eval "
        \$before = memory_get_usage(true);
        if (class_exists('Mayflower_Magnavale_CSV_Exporter')) {
            \$e = new Mayflower_Magnavale_CSV_Exporter();
            \$csv = \$e->generate_csv($large_order);
        }
        \$after = memory_get_usage(true);
        \$used_mb = round((\$after - \$before) / 1024 / 1024, 2);
        \$peak_mb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        echo \"used:\$used_mb|peak:\$peak_mb\";
    " 2>/dev/null)
    
    log_info "  Memory: $MEM_RESULT"
    PEAK=$(echo "$MEM_RESULT" | grep -oP 'peak:\K[0-9.]+')
    if [[ -n "$PEAK" ]]; then
        assert_true "Peak memory under 128MB" "[[ \$(echo '$PEAK < 128' | bc -l) -eq 1 ]]"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════
# TEST: FTP upload concurrency (if credentials available)
# ══════════════════════════════════════════════════════════════════════════
if [[ "$SKIP_FTP" != "1" && -n "$FTP_PASS" ]]; then
    log_info "Testing concurrent FTP uploads..."
    
    FTP_PIDS=()
    FTP_RESULTS="/tmp/mvtest_ftp_conc_${TIMESTAMP}"
    mkdir -p "$FTP_RESULTS"
    
    for i in $(seq 1 3); do
        (
            echo "col1,col2" > "/tmp/mvtest_conc_${i}.csv"
            echo "val${i}_1,val${i}_2" >> "/tmp/mvtest_conc_${i}.csv"
            
            wp_cmd eval "
                \$conn = @ftp_ssl_connect('$FTP_HOST', 21, 30);
                if (!\$conn) { echo 'CONNECT_FAIL'; exit; }
                if (!@ftp_login(\$conn, '$FTP_USER', '$FTP_PASS')) { echo 'LOGIN_FAIL'; exit; }
                ftp_pasv(\$conn, true);
                \$r = @ftp_put(\$conn, 'TEST_conc_${i}_${TIMESTAMP}.csv', '/tmp/mvtest_conc_${i}.csv', FTP_ASCII);
                echo \$r ? 'UPLOAD_OK' : 'UPLOAD_FAIL';
                @ftp_delete(\$conn, 'TEST_conc_${i}_${TIMESTAMP}.csv');
                ftp_close(\$conn);
            " > "${FTP_RESULTS}/ftp_${i}.txt" 2>/dev/null
            rm -f "/tmp/mvtest_conc_${i}.csv"
        ) &
        FTP_PIDS+=($!)
    done
    
    for pid in "${FTP_PIDS[@]}"; do
        wait "$pid" 2>/dev/null
    done
    
    FTP_OK=0
    for f in "${FTP_RESULTS}"/ftp_*.txt; do
        [[ -f "$f" ]] && grep -q "UPLOAD_OK" "$f" && FTP_OK=$((FTP_OK + 1))
    done
    
    assert_equals "Concurrent FTP uploads all succeeded" "3" "$FTP_OK"
else
    skip_test "Concurrent FTP uploads" "FTP credentials not available"
fi

# Cleanup
rm -rf "$PARALLEL_DIR" "/tmp/mvtest_ftp_conc_${TIMESTAMP}" 2>/dev/null

end_suite
