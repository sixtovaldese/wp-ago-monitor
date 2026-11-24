=== aGo Monitor ===
Contributors: agolab
Donate link: https://paypal.me/sixtovaldes
Tags: site health, cron, ssl, 404, health report
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Health dashboard and opt-in weekly email measured inside your site: SSL days left, overdue cron events, autoload weight, sizes, 404s.

== Description ==

aGo Monitor puts the internal health figures of your site on one admin screen, and can email the same figures to you once a week. Everything is measured by the site itself, on its own server. There is no account to create and no external service involved.

**What sets it apart**

The weekly email is built and sent by your own site, with no account and no third-party dashboard. It reports the problems that build up inside WordPress and that the core Site Health screen does not email you: scheduled cron events that are overdue, the weight of autoloaded options against a threshold you set, the days left on the SSL certificate, plugins with a newer version and the number of 404s in the last seven days.

**What the screen shows**

* SSL certificate: days until it expires, read from your own domain.
* Cron events: how many are scheduled, which ones are more than ten minutes overdue, with the full list of hooks, schedules and next runs.
* Autoloaded options: total size, with a warning above the threshold you choose and an error above 1500 KB.
* Database: size and number of tables of the database WordPress uses.
* Folder sizes: the uploads folder and the WordPress folder, measured at most once an hour.
* Pending updates: plugins with a newer version, as WordPress last checked them.
* System: PHP, WordPress and database server versions.
* 404 log: address, referrer and time of each request that ended in a 404, kept for 30 days.

**What it cannot do**

A plugin runs inside the site it watches, so it cannot tell you when the site is down: if the server or WordPress stops, the plugin stops with it. Use an uptime service that checks the site from outside for that. The weekly email is sent by WP-Cron, which runs when the site receives visits (or when a server cron calls it), so on a site that is down or has no traffic the email does not go out. Its absence can be a warning sign in itself.

== Installation ==

1. Upload the `ago-monitor` folder to `/wp-content/plugins/` or install it from the Plugins screen.
2. Activate the plugin.
3. Go to aGo Tools, then Monitor.
4. To receive the weekly email, turn on Weekly Report in the Settings card, optionally set the address, and save.

== Frequently Asked Questions ==

= Can it tell me when my site is down? =

No. It cannot tell you when the site is down, because it runs inside that same site. Use an external uptime service for that and use aGo Monitor for what happens inside WordPress.

= Does it send any of my data to an external service? =

No. Every check runs on your own server. The only connection it opens is a TLS handshake from your server to your own domain on port 443, to read the expiry date of its certificate. It does not contact WordPress.org either: pending updates are read from what WordPress already checked.

= When is the weekly email sent? =

Only if you turn it on. It is sent once a week by WP-Cron through wp_mail() and your site's own mail setup. WP-Cron runs when the site receives visits, so on a quiet site it can be late unless a server cron triggers it.

= Why does the SSL card say it cannot connect? =

Your server could not open a verified TLS connection to its own domain: the certificate may be expired or invalid, or the server may not be able to reach its own public address. Check the certificate with an external SSL test.

= What does the 404 log store? =

The requested address, the referrer and the time, in a table of your own database. It does not store IP addresses or browser data. Entries older than 30 days are removed, you can clear the log at any time, and you can turn the log off.

= Who can see the screen? =

Administrators (users who can manage options). On multisite it is shown only to network administrators, because the database size, the WordPress folder size and the cron list cover the whole network.

= Is the plugin translated? =

The plugin uses your WordPress site language. Translations are contributed through translate.wordpress.org, and the screens show in English until one exists for your language.

== Screenshots ==

1. The Monitor screen: SSL status, database, folder sizes, autoloaded options and pending updates.
2. Cron events with their schedule and next run, and the 404 log.
3. Settings: the 404 log, the opt-in weekly email and the autoload threshold.

== External services ==

This plugin does not connect to any third-party service and sends no data to anyone.

To show the SSL certificate expiry, your server opens a TLS connection to your own site's hostname (the host of home_url()) on port 443 and reads the certificate dates. The request stays between your server and your own domain.

The optional weekly report is sent through WordPress wp_mail() with your site's own mail setup, to the address you enter or to the site admin email.

The settings screen links to external sites that only open when you click them: the aGo Lab website and documentation, the WordPress developer documentation, SSL Labs and PayPal donation pages. No data is transmitted automatically.

== Privacy ==

* Settings are stored in the agomonitor_settings option.
* The 404 log is a table in your own database (prefix plus agomonitor_404s) with the requested address, the referrer and the time. It stores no IP address and no browser data. A request address or referrer can still include whatever a visitor typed in it, so the log keeps entries for 30 days only, and you can clear it or turn it off.
* Folder sizes are cached for an hour in the agomonitor_disk_uploads and agomonitor_disk_total transients.
* The weekly email goes to the address you set or to the site admin email, and contains only the figures shown on the screen.
* On deactivation the weekly cron event is removed. On uninstall the option, the transients, the 404 table and the cron event are deleted.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
