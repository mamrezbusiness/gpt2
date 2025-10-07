=== Woo Order Hours ===
Contributors: gptdev
Requires at least: 6.5
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: woocommerce, scheduling, checkout

WooCommerce extension that limits ordering to specific business hours with cache-safe notices.

== Description ==

Woo Order Hours helps stores operating under strict schedules. Define weekly business hours, override special dates, and block checkout when the store is closed. Visitors always see an up-to-date status banner thanks to an AJAX-powered notice that plays nicely with page caches.

* Weekly opening intervals with overnight support.
* Holiday and special date overrides.
* Optional countdown until the next opening.
* Server-side checkout enforcement with optional cart restrictions.
* AJAX banner and REST endpoint for headless or cached environments.
* HPOS compatible.

== Installation ==

1. Upload the `woo-order-hours` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WooCommerce → Settings → Order Hours** to configure your schedule.

== Frequently Asked Questions ==

= Does it support High-Performance Order Storage (HPOS)? =

Yes, the plugin only uses public WooCommerce APIs and does not access legacy order tables directly.

= Will cached pages show the correct status? =

Yes. The frontend banner is rendered after page load via AJAX so any cached HTML remains accurate.

== Changelog ==

= 1.0.0 =
* Initial release.
