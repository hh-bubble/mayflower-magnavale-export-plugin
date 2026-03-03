# Mayflower → Magnavale Export Plugin — Testing & Validation Report

**Prepared by:** Bubble Design
**Date:** 3 March 2026
**Status:** Testing in progress

---

## 1. Overview

This plugin automates the daily export of WooCommerce orders from the Mayflower online shop to Magnavale's warehouse system. Each weekday at 4:13pm, all new orders are compiled into two CSV files (an Order file and a Packing List file), which are then uploaded securely to Magnavale's server via encrypted file transfer. The plugin handles box calculations, ice pack requirements, delivery date scheduling, and packaging material allocation automatically.

---

## 2. What's Been Tested & Verified

| Area | What Was Tested | Test Script | Status |
|------|----------------|-------------|--------|
| Product SKU audit | All 47 Mayflower product SKUs exist in WooCommerce and match Magnavale's database | `01-product-sku-audit.sh` | ✅ Passed |
| No duplicate SKUs | Every product SKU is unique — no duplicates in the database | `01-product-sku-audit.sh` | ✅ Passed |
| No missing SKUs | All published products have a SKU assigned | `01-product-sku-audit.sh` | ✅ Passed |
| Product prices set | All products have prices configured | `01-product-sku-audit.sh` | ✅ Passed |
| Single item orders | One order per product category (dim sum, noodle tray, sauce pot, sauce jar, dry mix, battered, rolls) | `02-order-creation.sh` | ✅ Passed |
| Multi-item orders | Mixed frozen, mixed ambient, and frozen + ambient combination orders | `02-order-creation.sh` | ✅ Passed |
| Large quantity orders | High quantity single product and large mixed catering orders | `02-order-creation.sh` | ✅ Passed |
| Complete product coverage | Single order containing all 47 products | `02-order-creation.sh` | ✅ Passed |
| Duplicate SKU in order | Same product added as multiple line items in one order | `02-order-creation.sh` | ✅ Passed |
| Similar name products | Retail vs catering variants with similar names exported with correct SKUs | `02-order-creation.sh` | ✅ Passed |
| Order CSV format | No BOM, consistent column count, no null bytes, correct encoding | `03-csv-generation.sh` | ✅ Passed |
| Order CSV content | Order ID, postcode, and product SKUs appear correctly in each row | `03-csv-generation.sh` | ✅ Passed |
| Special character handling | Apostrophes, hyphens, and accented characters preserved safely in CSV | `03-csv-generation.sh` | ✅ Passed |
| Quantity accuracy | Product quantities correctly reflected in CSV output | `03-csv-generation.sh` | ✅ Passed |
| Small box allocation | Orders with 1–18 pieces get 1 small box (5OSS) with inserts | `04-packaging-logic.sh` | ✅ Passed |
| Large box allocation | Orders with 19–33 pieces get 1 large box (5OSL) with inserts | `04-packaging-logic.sh` | ✅ Passed |
| Ice packs — frozen orders | Frozen product orders include 11DRYICE and 11ICEPACK in packing CSV | `04-packaging-logic.sh` | ✅ Passed |
| Ice packs — ambient orders | Ambient-only orders also receive ice packs (all boxes get ice) | `04-packaging-logic.sh` | ✅ Passed |
| Packaging quantities | Each packaging SKU appears correctly (not duplicated) | `04-packaging-logic.sh` | ✅ Passed |
| Packaging SKUs not orderable | Packaging codes (5OSL, 5OSS, etc.) are injected by plugin, not WooCommerce products | `04-packaging-logic.sh` | ✅ Passed |
| Box size boundary | Tested increasing quantities to verify small-to-large box threshold | `04-packaging-logic.sh` | ✅ Passed |
| FTPS connectivity | Encrypted FTPS connection (FTP over TLS) to Magnavale's server | `05-ftp-upload.sh` | ✅ Passed |
| FTPS directory access | Can access and write to remote directory | `05-ftp-upload.sh` | ✅ Passed |
| FTPS passive mode | Passive mode works correctly through firewall | `05-ftp-upload.sh` | ✅ Passed |
| Filename safety | Filenames sanitised against path traversal | `05-ftp-upload.sh` | ✅ Passed |
| Zero quantity handling | Orders with zero quantity items don't crash the export | `06-edge-cases.sh` | ✅ Passed |
| Very large quantity | Order with 9999 units of one product handled correctly | `06-edge-cases.sh` | ✅ Passed |
| Cancelled order exclusion | Cancelled orders are not included in export | `06-edge-cases.sh` | ✅ Passed |
| Refunded order handling | Refunded orders handled without errors | `06-edge-cases.sh` | ✅ Passed |
| Unicode characters | Names with umlauts, accents, and special characters (Muller, Odegaard, Strasse) | `06-edge-cases.sh` | ✅ Passed |
| Very long addresses | Long address fields (Welsh place names) don't crash or truncate | `06-edge-cases.sh` | ✅ Passed |
| Missing shipping address | Billing address used as fallback when shipping address is empty | `06-edge-cases.sh` | ✅ Passed |
| CSV injection prevention | Formula characters (=CMD, +HYPERLINK, -1+1\|cmd, @SUM) stripped from all text fields | `06-edge-cases.sh` | ✅ Passed |
| Non-existent order | Invalid order ID returns empty result, no crash | `06-edge-cases.sh` | ✅ Passed |
| Duplicate export (idempotency) | Same order generates identical CSV on repeated exports | `06-edge-cases.sh` | ✅ Passed |
| Concurrent timestamps | Filename uniqueness for orders processed at the same second | `06-edge-cases.sh` | ✅ Passed |
| No world-writable files | Plugin files have correct permissions | `07-security-audit.sh` | ✅ Passed |
| CSV directory not web-accessible | Archive directory returns 403/404, not 200 | `07-security-audit.sh` | ✅ Passed |
| No raw SQL queries | All database queries use parameterised WooCommerce APIs | `07-security-audit.sh` | ✅ Passed |
| XSS output escaping | Unescaped echo statements within acceptable range | `07-security-audit.sh` | ✅ Passed |
| Nonce verification | All admin AJAX actions protected with nonce checks | `07-security-audit.sh` | ✅ Passed |
| Capability checks | Admin actions require `manage_woocommerce` capability | `07-security-audit.sh` | ✅ Passed |
| No hardcoded credentials | No plaintext passwords in source code | `07-security-audit.sh` | ✅ Passed |
| No credentials in logs | Error logs don't contain passwords or sensitive data | `07-security-audit.sh` | ✅ Passed |
| Uses FTPS (not plain FTP) | `ftp_ssl_connect` used — all transfers encrypted with TLS | `07-security-audit.sh` | ✅ Passed |
| No PII in transients | Customer data not cached in WordPress transients | `07-security-audit.sh` | ✅ Passed |
| Direct access prevention | All PHP files have ABSPATH or CLI-only guards | `07-security-audit.sh` | ✅ Passed |
| Path traversal prevention | Malicious filenames sanitised (../, ;, $()) | `07-security-audit.sh` | ✅ Passed |
| Export deduplication | Code prevents orders being exported multiple times | `07-security-audit.sh` | ✅ Passed |
| Rapid sequential orders | 10 orders created rapidly all process correctly | `08-concurrency-stress.sh` | ✅ Passed |
| Parallel CSV generation | Multiple CSV generations running simultaneously produce correct output | `08-concurrency-stress.sh` | ✅ Passed |
| Large order stress | All-products order with qty x3 — memory and performance acceptable | `08-concurrency-stress.sh` | ✅ Passed |
| Memory usage | Export stays within 32MB memory differential | `08-concurrency-stress.sh` | ✅ Passed |
| Idempotent CSV output | Same order always produces identical CSV (hash comparison) | `09-data-integrity.sh` | ✅ Passed |
| Customer data accuracy | Name, postcode, and address match between WooCommerce and CSV | `09-data-integrity.sh` | ✅ Passed |
| SKU-to-product mapping | Similar-named products mapped to correct SKUs | `09-data-integrity.sh` | ✅ Passed |
| Quantity accuracy across aggregation | Packing list totals match sum of individual order quantities | `09-data-integrity.sh` | ✅ Passed |
| No data leakage between orders | Order A's customer data never appears in Order B's CSV rows | `09-data-integrity.sh` | ✅ Passed |
| Export status tracking | Order metadata correctly tracks export status and timestamps | `09-data-integrity.sh` | ✅ Passed |
| Test cleanup | All test orders and temp files cleaned up after test run | `10-regression-cleanup.sh` | ✅ Passed |
| Bundle expansion in CSV | Normal products appear in CSV; no hash-like bundle SKUs leak through | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Bundle type safety net | WooCommerce bundle-type products filtered out even without ACF metadata | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Mixed frozen + ambient order | All food SKUs appear in CSV with correct ice packs in packing list | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Very large order (67+ pieces) | Multiple box allocation, labels match box count, ice packs positive | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Box boundary (66/67 pieces) | Exact boundary between 2-large-box and 67+ tiers tested | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Guest checkout | Customer ID = 0, order exports correctly with all other fields | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Missing SKU handling | Products without SKU get MISSING_SKU_ marker, don't crash export | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Export deduplication via status | Exported orders excluded from collector; status change doesn't re-flag | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Empty batch handling | Collector returns no orders for non-existent status, no crash | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Ice pack code regression | 11DRYICE and 11ICEPACK present in packing CSV; no legacy DRYICE1KG/ICEPACK | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| DPD service code | 1^12 confirmed present in CSV output | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| KING01 account ref | KING01 appears in every row of the order CSV | `11-bundle-and-coverage-gaps.sh` | ✅ Passed |
| Single small order | 1 order, 2 items — small box scenario | `manual/01-single-small-order.php` | ✅ Passed |
| Single large order | 1 order, 14 products — large box scenario | `manual/02-single-large-order.php` | ✅ Passed |
| Multiple orders batch | 5 varied orders exported as a batch | `manual/03-multiple-orders.php` | ✅ Passed |
| Edge case names | Apostrophes, accents, Welsh names in customer data | `manual/04-edge-case-names.php` | ✅ Passed |
| Boundary quantities | 8 orders at exact box tier thresholds (18, 19, 33, 34, 51, 52, 66) | `manual/05-boundary-quantities.php` | ✅ Passed |
| Sauce-only order | Ambient products only — no frozen items | `manual/06-sauce-only-order.php` | ✅ Passed |
| Mixed categories | Every product type in a single order | `manual/07-mixed-categories.php` | ✅ Passed |
| Stress test (20 orders) | 20 randomised orders exported as a batch | `manual/08-stress-test-20-orders.php` | ✅ Passed |
| Stress test (50 orders) | 50 randomised orders exported as a batch | `manual/09-stress-test-50-orders.php` | ✅ Passed |
| Duplicate products | Same product as multiple line items in one order | `manual/10-duplicate-products.php` | ✅ Passed |
| Max single order | Every product in the catalogue in one order | `manual/11-max-single-order.php` | ✅ Passed |
| Bundle exclusion | 3 scenarios: bundle-only, bundle + normal, 2 bundles + normal | `manual/12-bundle-exclusion-test.php` | ✅ Passed |
| Zero orders day | Empty export — no pending orders to process | `manual/13-zero-orders-day.php` | ✅ Passed |
| CSV injection vectors | =CMD(), +cmd\|, @SUM(), =IMPORTRANGE() in customer fields | `security/test-csv-injection.php` | ✅ Passed |
| XSS in fields | Script tags and onerror handlers in order data | `security/test-xss-in-fields.php` | ✅ Passed |
| SQL injection fields | SQL injection patterns in customer and address fields | `security/test-sql-injection-fields.php` | ✅ Passed |
| Path traversal | ../../etc/passwd patterns in filenames and fields | `security/test-path-traversal.php` | ✅ Passed |
| Oversized fields | 500-char names, 10k-char addresses | `security/test-oversized-fields.php` | ✅ Passed |
| Unicode injection | Emoji, RTL override, null bytes, non-Latin scripts | `security/test-unicode-injection.php` | ✅ Passed |

---

## 3. In Progress Testing Plan

| Test | Method | Expected Outcome | Status |
|------|--------|-------------------|--------|
| Manual order via staging website | Place order through checkout at mayflower.bubblestaging.com | Order appears in next day's export CSV | 🔲 Pending |
| Bundle order via checkout | Order containing a bundle product (e.g. Mayflower Mixes Bundle) | Only individual products appear in CSV, not the bundle itself | 🔲 Pending |
| Mixed order (frozen + ambient) | Order with both frozen and ambient items | All products exported correctly with correct ice pack allocation | 🔲 Pending |
| Guest checkout order | Place order without creating a customer account | Order exports correctly with customer ID = 0 | 🔲 Pending |
| Daily cron verification | Check export logs and output files each day Monday-Friday | CSV files generated and uploaded at 4:13pm | 🔲 In progress |
| Empty day handling | Day with no new orders | Cron runs, logs "no orders", no files uploaded | 🔲 Pending |
| Large order stress test | Order with 67+ pieces via checkout | Correct multi-box allocation | 🔲 Pending |
| CSV validation by Magnavale | Send exported CSV files to Magnavale for import test | Files import correctly into Magnavale's warehouse system | 🔲 Awaiting Magnavale |

*This is a living document. Statuses will be updated as testing progresses.*
