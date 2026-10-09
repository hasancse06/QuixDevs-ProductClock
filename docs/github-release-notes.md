# GitHub release notes

Prepared copy for a future release. Nothing has been published. Confirm the publishing checklist before creating the tag or attaching the final package.

**Title:** QuixDevs ProductClock v1.0.0 – WooCommerce Product Scheduler  
**Tag:** `v1.0.0`  
**Asset:** `quixdevs-productclock.zip`

Copy the content below into the release description. After publication, replace the asset instruction with a verified download link if desired. The copy uses repository filenames rather than guessed remote URLs. Add confirmed repository documentation links when the owner and public repository exist.

---

QuixDevs ProductClock schedules WooCommerce products to publish and return to draft at chosen dates and times. Use it for product launches, seasonal catalogs and limited availability windows.

## Included in 1.0.0

- Opt-in Publish Only, Expire Only and Publish & Expire modes.
- Simple products and variable parent products in the classic WooCommerce editor.
- WordPress timezone support, UTC storage and strict daylight saving validation.
- WooCommerce Action Scheduler tasks with duplicate and stale-task protection.
- Manual status changes suspend automation until explicitly resumed.
- Dashboard, schedule list, settings, activity, diagnostics and recovery tools.
- Local operation without an external account or subscription.
- Data retention on uninstall by default, with optional ProductClock data removal.

Expiration returns a product to draft and preserves its product data. Scheduling is one-time. Execution occurs at or after the scheduled instant when cron and workers run; exact-second execution is not guaranteed.

## Download and install

Download **quixdevs-productclock.zip** from this release's **Assets**. Use the installable ZIP rather than GitHub's generated source archives.

With WooCommerce installed and active, open **WordPress Admin → Plugins → Add Plugin → Upload Plugin**, choose the ZIP, select **Install Now**, then **Activate Plugin**. Composer and command-line tools are not required. Open **WooCommerce → ProductClock** to check scheduler health, then **Product Data → ProductClock** on a product to create a schedule.

## Requirements and local verification

Declared minimums: WordPress 6.8, WooCommerce 10.0, PHP 7.4, and MySQL/MariaDB connection-scoped advisory locks with a stable writer connection.

Verified environment: **WordPress 6.8.3 / WooCommerce 10.0.4 / PHP 8.4.13 / MySQL 8.0.46**. Recorded checks passed: **41 tests, 78 assertions**, PHP syntax, PHPCS, runtime-enabled Plugin Check and WordPress ZIP upload/activation.

Broader compatibility, large-store stress tests and GitHub CI execution remain unverified. The classic product editor is supported; network activation and network-wide cleanup are unsupported. See the repository's `docs/testing.md`, `README.md`, `CHANGELOG.md` and `LICENSE` for details.

Maintained by QuixDevs. **GPL-2.0-or-later**.

---

## Maintainer package note — do not paste into release copy

Build the final archive from the reviewed repository using `python3 tools/build-release.py`. Run extracted-package Plugin Check and ZIP upload/activation checks, record its hash, and attach that exact file to the release. Release ZIPs are generated artifacts and are not committed to this repository. Keep branding, screenshots and development files outside the runtime ZIP. Update verification claims only when new checks actually ran; preserve outstanding limits.

Local handoff links: [README](../README.md) · [Testing](testing.md) · [Changelog](../CHANGELOG.md) · [License](../LICENSE) · [Publishing checklist](github-publishing-checklist.md).
