# Mayflower → Magnavale Export Plugin — Testing & Validation Report

**Prepared by:** Bubble Design
**Date:** 2 March 2026
**Status:** Testing in progress

---

## 1. Overview

This plugin automates the daily export of WooCommerce orders from the Mayflower online shop to Magnavale's warehouse system. Each weekday at 4:13pm, all new orders are compiled into two CSV files (an Order file and a Packing List file), which are then uploaded securely to Magnavale's server via encrypted file transfer. The plugin handles box calculations, ice pack requirements, delivery date scheduling, and packaging material allocation automatically.

---

## 2. What's Been Tested & Verified

| Area | What Was Tested | Status |
|------|----------------|--------|
| Plugin setup | Installation, activation, dependency loading on staging site | ✅ Passed |
| Product codes | All 47 Mayflower product SKUs match Magnavale's product database | ✅ Passed |
| Ice pack codes | Updated to confirmed codes: 11DRYICE (dry ice), 11ICEPACK (regular ice) | ✅ Passed |
| Bundle handling | Bundle orders correctly expanded into individual constituent products; bundle parent items excluded from CSV | ✅ Passed |
| Order CSV format | 19-column format matches Magnavale's specification (no header row, comma-delimited) | ✅ Passed |
| Packing CSV format | 15-column format matches Magnavale's specification (aggregated totals + packaging materials) | ✅ Passed |
| Account reference | KING01 confirmed and hardcoded in all CSV rows | ✅ Passed |
| Courier | DPD confirmed and hardcoded in all CSV rows | ✅ Passed |
| DPD service code | Confirmed as 1^12 (DPD Next Day by 12:00) | ✅ Passed |
| Box calculation | Small box (1-18 pieces), large box (19-33), mixed combinations (34+), large orders (67+) all calculate correctly | ✅ Passed |
| Ice packs per box | Small box: 3 dry ice + 3 regular. Large box: 4 dry ice + 5 regular | ✅ Passed |
| Packaging materials | Box codes (5OSL, 5OSS) and insert codes (5OSLI, 5OSLIS, 5OSSI, 5OSSIS) all correct | ✅ Passed |
| Delivery date calculation | Correct scheduling using batched cut-off model (4pm weekday cut-off) | ✅ Passed |
| Secure file transfer | Encrypted FTPS connection (FTP over TLS) to Magnavale's server | ✅ Passed |
| Automated daily export | Server cron scheduled at 4:13pm Monday-Friday | ✅ Running |
| Security audit | CSV injection prevention, admin access controls, encrypted credentials, input validation, no hardcoded passwords | ✅ Passed |
| Duplicate prevention | Orders marked as exported after successful upload; already-exported orders are not re-exported | ✅ Passed |
| Empty day handling | When no new orders exist, the system logs "no orders" and exits cleanly without uploading empty files | ✅ Passed |
| Guest checkout | Orders placed without a customer account export correctly (customer ID shows as 0) | ✅ Passed |
| Missing SKU handling | Products without a SKU are flagged with a marker and logged, rather than crashing the export | ✅ Passed |
| Unicode & special characters | Customer names with accents, apostrophes, and non-English characters are handled safely | ✅ Passed |
| Large order stress test | Orders with 67+ pieces correctly allocate multiple boxes with correct ice pack quantities | ✅ Passed |
| Data integrity | Customer details, product codes, and quantities are accurately transferred from WooCommerce to CSV | ✅ Passed |
| Concurrent export protection | File locking prevents multiple exports running at the same time | ✅ Passed |
| Email notifications | Success and failure emails sent to configured admin addresses after each export run | ✅ Passed |

---

## 3. This Week's Testing Plan

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

---

## 4. Confirmed Specifications

The following details have been confirmed by both parties and are locked in:

| Setting | Value | Confirmed By |
|---------|-------|--------------|
| Account code | KING01 | Magnavale |
| Courier | DPD | King Asia / Magnavale |
| DPD service code | 1^12 (Next Day by 12:00) | King Asia |
| Dry ice product code | 11DRYICE | Magnavale |
| Regular ice pack code | 11ICEPACK | Magnavale |
| Bundle handling | No specific Magnavale codes — individual constituent products are used | King Asia |
| Export schedule | 4:13pm Monday-Friday (server cron, not WordPress pseudo-cron) | Bubble Design |
| File transfer method | Encrypted FTPS (FTP over TLS) | Magnavale |
| Order CSV columns | 19 columns (A-S), no header row | Magnavale |
| Packing CSV columns | 15 columns (A-O), no header row | Magnavale |

---

## 5. Outstanding Items

| Item | Waiting On | Priority |
|------|-----------|----------|
| CSV file validation by Magnavale | Magnavale to confirm files import correctly into their warehouse system | High |
| Manual checkout testing on staging site | Holly / King Asia to place test orders through the website | High |
| Soft rollout sign-off | Both parties to agree on 2-week monitored rollout start date | Medium |
| Go-live date confirmation | All parties | Medium |

---

## 6. Next Steps

1. **This week:** Complete manual and automated testing on the staging site. Place real orders through the checkout to verify the full pipeline end-to-end.

2. **CSV validation:** Send the first batch of test export files to Magnavale for them to test importing into their warehouse system. Any format issues will be resolved before rollout.

3. **2-week soft rollout:** Once Magnavale confirms the CSV files import correctly, begin a monitored rollout period. During this time, exports will run daily with the development team monitoring logs and output for any issues.

4. **Go-live:** After the soft rollout period completes successfully with no issues, the system moves to full production use.

---

*This is a living document. Statuses will be updated as testing progresses this week.*
