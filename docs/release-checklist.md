# Release checklist

## Completed locally

- Independent QuixDevs branding, GPL-2.0-or-later notice, correct dependency/text-domain headers.
- No invented author URL, company GitHub URL, telemetry or external runtime services.
- Opt-in simple/variable-parent lifecycle, versioned queue tasks, per-product mutexes and manual-override suspension.
- Native product controls, dashboard/settings/list/activity/tools and scoped assets.
- Strict dates, UTC persistence, timezone preservation and DST rejection.
- Nonces, product-specific edit permissions, global administrative permissions and POST-only confirmed tools.
- Bounded recovery/list/log operations, safe deactivation/reactivation and opt-in uninstall removal.
- Automated integration tests, WordPress coding standards, syntax validation, Plugin Check and clean archive inspection. See [recorded verification](testing.md#recorded-verification) for exact outcomes.
- Actual synthetic-store screenshots, README, readme.txt, changelog, schema/scheduler/timezone/testing documentation and translation template.

## Before public release

- Run the configured GitHub CI matrix and expand it to the currently supported WordPress/WooCommerce releases.
- Perform staging tests with the store's third-party plugins, persistent cache and server cron.
- Verify keyboard/screen-reader behavior and date controls across supported browsers, plus large enrolled-catalog recovery timings.
- Confirm QuixDevs' publishing account and security reporting channel; add confirmed URLs only when available.
- Review name/trademark availability with WordPress.org. The requested independent name uses “for WooCommerce”; approval remains the directory team's decision.
- Rebuild the ZIP and run Plugin Check on the extracted archive after any change.
- Confirm default data retention and document release-specific changes.
- Submit only after human release review. No GitHub publication, deployment or WordPress.org submission occurred here.

## Packaging

Run `python3 tools/build-release.py`. The archive has a single `quixdevs-productclock/` root and includes bootstrap, uninstall, classes, assets, languages, readme, license and changelog. Tests, vendor dependencies, docs/screenshots, CI, local config, caches and tools are excluded. The script verifies CRCs and the distribution allowlist and reports SHA-256.

## Presentation assets

The repository includes five [genuine screenshots](screenshots/README.md), [editable branding](branding/README.md), [GitHub listing and topics](github-listing.md), [release notes](github-release-notes.md) and a [publishing checklist](github-publishing-checklist.md). Future directory icons, banners and screenshot copies are in [wordpress-org-assets](../wordpress-org-assets/README.md), outside the runtime package.
