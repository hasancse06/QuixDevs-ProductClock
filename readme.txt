=== QuixDevs ProductClock – Product Scheduler for WooCommerce ===
Tags: woocommerce, product scheduler, scheduled publishing, product expiration, automation
Requires at least: 6.8
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Schedule WooCommerce products to publish and expire automatically. Set availability windows with timezone support and native product controls.

== Description ==

QuixDevs ProductClock is a free WooCommerce product scheduler for launches, seasonal catalogs and limited availability windows. Choose when a product becomes published and when it returns to draft. Schedule. Publish. Expire.

Configure automatic product publishing and product expiration in Product Data → ProductClock, using the classic WooCommerce product editor. Each product opts in separately.

= Key features =

* Publish Only, Expire Only and Publish & Expire modes.
* Simple products and variable parent products; no independent variation scheduling.
* Native controls with dates, timezone, schedule status and next action.
* WordPress site timezone or UTC for new schedules, with UTC storage.
* WooCommerce Action Scheduler with duplicate and stale-task protection.
* Manual status changes suspend automation until explicitly resumed.
* Dashboard, schedule list, activity, settings, diagnostics and recovery tools.
* Expiration returns products to draft and retains product data.
* Local operation without telemetry, external accounts, paid licenses or remote APIs.

ProductClock does not schedule discounts, manage subscriptions or enforce an order deadline. Independent software by QuixDevs; no official WooCommerce endorsement is implied.

= Requirements and verification =

Requires WordPress 6.8+, active WooCommerce 10.0+, PHP 7.4+, and MySQL/MariaDB advisory locks with a stable writer connection.

Verified locally: WordPress 6.8.3, WooCommerce 10.0.4, PHP 8.4.13, MySQL 8.0.46; 41 tests, 78 assertions, PHP syntax, PHPCS, runtime-enabled Plugin Check and ZIP upload/activation passed. Broader compatibility, large-store stress tests and GitHub CI execution remain unverified. Minimum versions are intended API baselines, not individually tested environments.

= Scheduling limits =

Execution occurs at or after the due time when workers run. WP-Cron depends on traffic unless server cron is configured; exact-second execution is not guaranteed.

Classic editor and one-time schedules only. Network activation and network-wide cleanup are unsupported; multisite lifecycle remains unverified. See the FAQ for status, timezone and retention behavior.

== Installation ==

1. Install and activate WooCommerce first.
2. In WordPress Admin, open Plugins → Add Plugin → Upload Plugin.
3. Choose the installable quixdevs-productclock.zip, select Install Now, then Activate Plugin. Composer and command-line tools are not needed on your store.
4. Open WooCommerce → ProductClock and review Scheduler Health.
5. Edit a simple product or variable parent, open Product Data → ProductClock and enable scheduling.
6. Choose a mode, enter dates, verify the timezone and save. Keep future launch products in draft, using Save Draft.
7. Check Current Schedule Status and Next Scheduled Action.

== Frequently Asked Questions ==

= What does product expiration do? =
It returns a published product to draft. It does not delete the product or change its prices, stock, categories, attributes or images.

= Can I expire without a publication date? =
Yes. Choose Expire Only on a published product.

= Can ProductClock draft a published product before a future launch? =
It does not silently do so. Move the product to draft yourself before scheduling future publication.

= What happens if both dates have passed? =
Publication is suppressed. A published product returns to draft; a draft remains draft.

= How are timezones handled? =
New schedules use the WordPress site timezone or UTC, as configured. Existing schedules keep their captured timezone and UTC instants when site settings change. Use a named timezone for daylight saving rules. Nonexistent and repeated daylight saving times are rejected. No per-record timezone reassignment control is provided.

= What happens when I manually change product status? =
Automation is suspended, including after trash/restoration. Review the ProductClock panel, explicitly select Resume this schedule, and save. Completed dates remain completed. Private, pending-review, future and trashed products are never automatically changed.

= Can I edit, cancel or repeat a schedule? =
Change dates and save to replace pending jobs. Disable scheduling and save to cancel pending work. Version 1.0.0 supports one-time schedules; enter new dates for a new lifecycle. An unchanged save does not rearm completed dates.

= Why is execution delayed? =
WP-Cron depends on traffic unless server cron is configured. Check WooCommerce → Status → Scheduled Actions and ProductClock Scheduler Health. Configure WP-CLI cron event run --due-now every minute using the site's path and PHP environment. Verify this before disabling traffic-driven cron. Tools can repair missing actions; suspended/error schedules require explicit review and resume.

= Where can I see logs? =
WooCommerce → ProductClock → Activity shows up to twenty recent events per product for thirty days when logging is enabled. Full WooCommerce logs use source quixdevs-productclock and follow native retention. Dashboard and Tools show scheduler diagnostics and recovery options.

= What happens on deactivation and uninstall? =
Deactivation cancels ProductClock pending tasks and preserves product statuses and schedule data. Reactivation reconstructs valid work. An already-started status write may finish. Uninstall retains data by default. Enable Delete ProductClock data on uninstall to remove only ProductClock metadata, options and scheduler records. Native WooCommerce logs follow its retention policy.

= Is an external account or subscription required? =
No. Scheduling runs locally without an external service, telemetry or paid license.

= Which product editor and product types are supported? =
The classic WooCommerce product editor, simple products and variable parent products. Variations are not scheduled independently.

== Screenshots ==

1. Product scheduling controls: enable Publish & Expire, choose dates, review the timezone and see the current schedule status and next action.
2. Dashboard: scheduling statistics, upcoming publication and expiration transitions, and scheduler health.
3. Scheduled products: synthetic products with different scheduling states, modes, publication dates and expiration dates.
4. Settings: global scheduling switches, activity logging, timezone for new schedules and optional uninstall data removal.
5. Activity: genuine successful publication and expiration records, reconciliation recovery and manual override information.

== Changelog ==

= 1.0.0 =
* Initial release: per-product publication and expiration, native administration, versioned Action Scheduler jobs, recovery, UTC timezones and manual override protection.
