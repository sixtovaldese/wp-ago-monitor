=== aGo Monitor ===
Contributors: agolab
Donate link: https://paypal.me/sixtovaldes
Tags: monitor, health, uptime, ssl, 404
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WordPress health monitor: SSL expiry, DB size, disk usage, autoloaded options, cron health, updates, 404 log and weekly email report.

== Description ==

aGo Monitor gives a quick at-a-glance view of your site health from the WordPress admin: SSL certificate expiry, database size, disk usage, autoloaded options weight, cron health, pending plugin updates and recent 404 errors. Optional weekly email report.

**Features**

* SSL certificate status and days until expiry.
* Database size, table count.
* Uploads and total disk usage.
* Autoloaded options size.
* Cron job count and overdue events.
* Pending plugin updates.
* 404 error log.
* Optional weekly health report by email.
* All checks run locally on your server. No tracking, no third-party analytics.

== Installation ==

1. Upload `ago-monitor` to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **aGo Tools, Monitor**.

== Frequently Asked Questions ==

= Does it send any of my data to an external service? =

No. Every check runs on your own server. The only outbound connection is a TLS handshake to your own site's domain on port 443 to read its SSL certificate expiry date.

= How is the SSL expiry detected? =

The plugin opens a short TLS connection to your site's own hostname (from home_url()) and reads the certificate's validity dates. No data is sent to any third party.

= Where is the 404 log stored? =

In a custom database table in your own WordPress database. You can clear it anytime from the Monitor screen, and it is removed entirely on uninstall.

= How often is the email report sent? =

Weekly, only if you enable it. It uses WordPress wp_mail() and your site's own mail configuration.

== External services ==

This plugin does not connect to any third-party service. To report your SSL certificate expiry, it opens a single TLS connection to your own site's hostname (the host returned by home_url()) on port 443 and reads the certificate dates locally. No request leaves your own domain and no data is shared with anyone. The optional weekly report is sent through WordPress wp_mail() using your site's own mail setup.

== Privacy ==

* Plugin settings are stored locally in the wp_options table (option agomonitor_settings).
* The 404 logger stores the requested URL, referrer, user agent, visitor IP and timestamp in a custom table in your own database for diagnostics. This data stays on your server and is never sent to any third party. You can disable the 404 logger in the settings.
* On uninstall, the agomonitor_settings option, the disk-usage transients, the 404 table and the weekly cron event are all removed.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
