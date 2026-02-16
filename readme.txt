=== Mayflower Magnavale Export ===
Contributors: Bubble Design & Marketing Ltd
Tags: woocommerce, export, csv, magnavale, fulfillment, dpd
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
WC requires at least: 6.0
WC tested up to: 8.6
Stable tag: 1.0.0
License: Proprietary

Exports WooCommerce orders as CSV files in Magnavale's required format and uploads them via SFTP for fulfillment.

== Description ==

This plugin automates the process of sending order data from the Mayflower WooCommerce store to Magnavale (cold storage/fulfillment partner). It generates two CSV files daily:

1. **Order CSV** — One row per product line item per order (19 columns)
2. **Packing List CSV** — Aggregated product totals + packaging materials (15 columns)

Both files are uploaded to an SFTP server where Magnavale retrieves them for processing.

== Features ==

* Automatic daily export via server cron
* Manual export button in WP Admin
* Delivery date calculation based on cut-off windows
* Box type and ice pack calculation
* SFTP upload with retry and error handling
* Export status tracking on each order
* Full export log with history
* HPOS compatible (WooCommerce High-Performance Order Storage)
* Encrypted SFTP credential storage

== Installation ==

1. Upload the `mayflower-magnavale-export` directory to `/wp-content/plugins/`
2. Install phpseclib via Composer: `composer require phpseclib/phpseclib:~3.0`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce → Magnavale Export to configure SFTP credentials
5. Set up a server cron job to run the export daily at 16:01 UK time:
   `CRON_TZ=Europe/London`
   `1 16 * * * /usr/bin/flock -n /tmp/mme-export.lock /usr/bin/php /path/to/wp-content/plugins/mayflower-magnavale-export/cron-export.php >> /var/log/mme-export.log 2>&1`

== Configuration ==

Navigate to WooCommerce → Magnavale Export in the admin menu.

* **SFTP Settings** — Server, port, username, password (stored encrypted)
* **Account Settings** — KING01, DPD, 1^12 (defaults pre-filled)
* **Order Cut-off** — Cut-off time for order inclusion in each delivery window
* **Alert Email** — Receives notifications on export failure

== Requirements ==

* WordPress 5.8+
* WooCommerce 6.0+
* PHP 7.4+ with OpenSSL extension
* phpseclib 3.x (loaded via Composer)
* Outbound SFTP access on the server (port 22)
* Server-level cron job (the plugin does NOT use WP-Cron)

== Changelog ==

= 1.0.0 =
* Initial release
