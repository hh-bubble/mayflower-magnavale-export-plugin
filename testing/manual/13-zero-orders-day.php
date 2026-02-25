#!/usr/local/bin/php.cli
<?php
/**
 * Manual Test 13: Zero Orders Day
 * Creates nothing — tests empty export behaviour.
 * Run this, then trigger the export to verify:
 *   - No crash or error
 *   - No empty CSV uploaded to FTPS
 *   - Export log shows "No orders" message
 *
 * Run via SSH: /usr/local/bin/php.cli /path/to/testing/manual/13-zero-orders-day.php
 */

if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }

echo "=== Manual Test 13: Zero Orders Day ===" . PHP_EOL;
echo PHP_EOL;
echo "No orders created. This is intentional." . PHP_EOL;
echo PHP_EOL;
echo "Now trigger the export (manually or wait for the cron) and verify:" . PHP_EOL;
echo "  1. Plugin does NOT crash or produce PHP errors" . PHP_EOL;
echo "  2. No empty CSV files are uploaded to FTPS" . PHP_EOL;
echo "  3. Export log shows 'No pending orders found' or similar" . PHP_EOL;
echo "  4. No unnecessary alert emails are sent" . PHP_EOL;
echo PHP_EOL;
echo "If there are existing pending orders from other tests, clean them up first:" . PHP_EOL;
echo "  /usr/local/bin/php.cli /path/to/testing/cleanup.php" . PHP_EOL;
