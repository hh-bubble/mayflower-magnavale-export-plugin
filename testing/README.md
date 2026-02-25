# Mayflower Magnavale Export — Testing Suite

Testing framework for the 2-week soft rollout on staging before go-live.

**Staging site:** mayflower.bubblestaging.com
**Server:** s460.sureserver.com (ICDSoft, UTC-5)
**Plugin path:** `/home/mayflower/www/www/wp-content/plugins/mayflower-magnavale-export/`
**PHP CLI:** `/usr/local/bin/php.cli`

---

## Cron Job Setup (ICDSoft Hosting Panel)

You need **2 cron jobs** during the testing period (max 3 allowed):

### Cron 1: Test Order Creator (TEMPORARY — remove before go-live)

| Setting | Value |
|---|---|
| Script | `/home/mayflower/www/www/wp-content/plugins/mayflower-magnavale-export/testing/cron-create-test-orders.php` |
| Hour | `10` |
| Minute | Every 60 minutes |
| Day of week | Mon, Tue, Wed, Thu, Fri |

This fires at ~10:30 server time = ~15:30 UK time (43 min before export).
Creates 10-60 varied test orders covering all products daily.

**Don't forget:** `chmod 775` on the script after uploading:
```bash
chmod 775 /home/mayflower/www/www/wp-content/plugins/mayflower-magnavale-export/testing/cron-create-test-orders.php
```

### Cron 2: Magnavale Export (PERMANENT)

| Setting | Value |
|---|---|
| Script | `/home/mayflower/www/www/wp-content/plugins/mayflower-magnavale-export/cron-export.php` |
| Hour | `11` |
| Minute | Every 60 minutes |
| Day of week | Mon, Tue, Wed, Thu, Fri |

This fires at ~11:13 server time = ~16:13 UK time (after the 16:00 cut-off).

---

## Running Manual Tests

SSH into the server and run any script directly:

```bash
/usr/local/bin/php.cli /home/mayflower/www/www/wp-content/plugins/mayflower-magnavale-export/testing/manual/01-single-small-order.php
```

After creating orders, trigger the export either:
- Wait for the 16:13 cron, or
- Go to WP Admin > WooCommerce > Magnavale Export > Manual Export

### Available Manual Tests

| Script | What it does |
|---|---|
| `01-single-small-order.php` | 1 order, 2 items (1 small box) |
| `02-single-large-order.php` | 1 order, 14 products across categories |
| `03-multiple-orders.php` | 5 varied orders from different customers |
| `04-edge-case-names.php` | Apostrophes, accents, Welsh chars, commas |
| `05-boundary-quantities.php` | 8 orders at exact box size thresholds |
| `06-sauce-only-order.php` | Ambient products only (no frozen) |
| `07-mixed-categories.php` | Every product type in 1 order |
| `08-stress-test-20-orders.php` | 20 randomised orders |
| `09-stress-test-50-orders.php` | 50 randomised orders |
| `10-duplicate-products.php` | Same product as multiple line items |
| `11-max-single-order.php` | 1 order with every product in catalog |
| `12-bundle-exclusion-test.php` | Bundle products (must be excluded) |
| `13-zero-orders-day.php` | No orders (test empty export) |

### Security Tests

| Script | What it tests |
|---|---|
| `security/test-csv-injection.php` | `=CMD()`, `+cmd\|`, `@SUM()`, `\|calc` in fields |
| `security/test-xss-in-fields.php` | `<script>`, `<img onerror>` in name/address |
| `security/test-sql-injection-fields.php` | `'; DROP TABLE` patterns |
| `security/test-path-traversal.php` | `../../../../etc/passwd` in address |
| `security/test-oversized-fields.php` | 500-char name, 10,000-char address |
| `security/test-unicode-injection.php` | Emoji, RTL override, null bytes, Chinese/Arabic |

---

## Running Validation Scripts

After an export completes, validate the CSV output:

```bash
cd /home/mayflower/www/www/wp-content/plugins/mayflower-magnavale-export/
bash testing/validate/validate-csv-format.sh
bash testing/validate/validate-skus.sh
bash testing/validate/validate-delivery-dates.sh
bash testing/validate/validate-packaging.sh
bash testing/validate/validate-ftps-upload.sh
bash testing/validate/validate-no-csv-injection.sh
```

Or run all at once:
```bash
for v in testing/validate/*.sh; do echo ""; bash "$v"; done
```

---

## Cleanup

Before go-live, remove all test data:

```bash
# Preview what will be deleted
/usr/local/bin/php.cli testing/cleanup.php --dry-run

# Actually delete
/usr/local/bin/php.cli testing/cleanup.php
```

Options:
- `--dry-run` — show what would be deleted without deleting
- `--orders` — only delete test orders
- `--files` — only delete test log/result files

---

## Go-Live Checklist

1. Remove the test order creation cron from ICDSoft panel
2. Run `cleanup.php` to delete all test orders
3. Verify the export cron is still set up correctly
4. Confirm FTPS credentials are production (not staging)
5. Confirm Magnavale has received and parsed test CSVs successfully
6. Delete test CSV files from the FTPS server
7. Clear the plugin's archive folder of test data
