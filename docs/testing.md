# Testing

## Actual local environment

Tests use a newly initialized temporary MySQL 8.0.46 instance with networking disabled, separate disposable `qpc_tests` and `qpc_admin` databases, WordPress 6.8.3, WooCommerce 10.0.4 and PHP 8.4.13. No live store or existing database was used. The final suite passed 41 tests and 78 assertions. Recorded checks are summarized below; remaining limits are documented in the compatibility section.

The suite boots the official WordPress PHPUnit framework and real WooCommerce, explicitly selects `ActionScheduler_DBStore` and `ActionScheduler_DBLogger`, and creates real products and queue entries. It includes an actual queue-runner execution. Two independent database connections verify the advisory mutex excludes another worker. It does not claim a high-load multi-process stress test.

Cases cover bootstrap/dependencies, activation/deactivation/reactivation, schedule creation/update/disable, all three modes, due/future/past dates, stale/duplicate tasks, expiry precedence, invalid calendars/relationships, manual overrides/resume, trash/restore/deletion, private status, lost-action repair, recurring-job uniqueness, interrupted-write intent, global pause, DST gaps/folds/round trips, permissions/nonces, successful product-field save, recursion avoidance, bounded logs/queries, unchanged stock/price/SKU, variable parent and variation statuses, product duplication, settings authorization and uninstall retention/removal.

Separate WP-CLI checks load ProductClock with WooCommerce skipped to confirm a safe missing-dependency path. Runtime-enabled Plugin Check targets an extracted release, excluding development artifacts. Browser checks use synthetic products in the disposable admin site; screenshots in `screenshots/` are genuine captures.

## Recorded verification

These results come from local implementation verification on October 9, 2026; they are not claims of a completed GitHub CI run.

| Check | Recorded outcome |
| --- | --- |
| WordPress/WooCommerce integration suite | 41 tests, 78 assertions, passed |
| Real Action Scheduler database-store worker and two-connection advisory lock | Passed |
| PHP syntax, including 24 production files and both test files | Passed |
| PHPCS: WordPress standards and PHPCompatibilityWP | No errors or warnings |
| Composer strict validation | Passed |
| Runtime-enabled Plugin Check 2.1.0, including experimental checks | No findings on the implementation archive |
| Missing-WooCommerce bootstrap | Safe dependency failure |
| WordPress ZIP upload and activation | Verified on the disposable store |
| Updated presentation readme check | Official local Plugin Check `plugin_readme` check passed |

The five public screenshots were captured from the working disposable store using synthetic products. Original machine-specific logs and internal work reports are intentionally excluded from this repository. A fresh release archive must be built and checked from the current repository before distribution.

## Reproducing

Install Composer development dependencies inside the plugin directory:

```sh
composer install
composer lint
```

Use an **empty disposable test database**, not a store database. The WordPress framework resets its tables. Download dependencies and generate test configuration:

```sh
export QPC_ALLOW_TEST_DB_RESET=1
export QPC_TEST_DB_NAME=qpc_tests
export QPC_TEST_DB_HOST=127.0.0.1
export QPC_TEST_DB_USER=root
# Set QPC_TEST_DB_PASSWORD securely for your local disposable database.
bash tools/install-test-env.sh
export WP_TESTS_DIR=/tmp/qpc-tests-env/wordpress-develop-6.8.3/tests/phpunit
composer test
```

The test bootstrap refreshes cached WordPress role objects after WooCommerce grants its capabilities, so permission assertions start with the correct roles on a fresh database.

The helper requires a `qpc_` database prefix and explicit reset opt-in. For a Unix socket or a custom installation, provide a standard `wp-tests-config.php` to the official framework instead. Composer's platform baseline is PHP 7.4 so the committed lock file can install across the planned CI matrix.

Build and inspect the release:

```sh
python3 tools/build-release.py
python3 tools/verify-release.py
unzip -l dist/quixdevs-productclock.zip
```

On a disposable installed WordPress site with Plugin Check:

```sh
wp --require=wp-content/plugins/plugin-check/cli.php plugin check /absolute/path/to/extracted/quixdevs-productclock --include-experimental
```

PHP syntax checks include bootstrap, uninstall, production classes and tests. PHPCS uses WordPress standards plus PHPCompatibilityWP. Narrow exceptions document bounded indexed administrative postmeta queries and necessary mutex/keyset SQL. PSR-4 filenames are intentional. Docblock style checks are excluded; executable coding/security/i18n rules remain enabled.

## Compatibility claims and CI

Local verification covers PHP 8.4.13 / WordPress 6.8.3 / WooCommerce 10.0.4 only. Intended minimums are PHP 7.4 / WordPress 6.8 / WooCommerce 10.0. CI is configured for PHP 7.4, 8.2 and 8.4 against WordPress 6.8.3 and WooCommerce 10.0.4. These CI jobs have not been run on GitHub in this session. Expand and run the matrix against the then-current supported releases before public distribution; do not raise Tested up to without evidence.

MariaDB, persistent object-cache configurations, database proxies/reconnect behavior, multisite lifecycle and large-store stress benchmarks were not verified locally. Connection-scoped locks require a stable writer connection, as used by ordinary WordPress MySQL installations. The classic editor was checked in the available in-app browser, including its narrow responsive viewport; other browser/assistive-technology combinations need release QA. The beta product editor is outside scope.

## Release tooling tests

Run `python3 -m unittest discover -s tests/release -v` without WordPress or a database. These tests exercise deterministic ZIP output, developer-file exclusions, missing runtime classes/assets, dependency/tag mismatches, symlinks, ZIP tampering/traversal and generated WordPress.org assets. They run alongside the existing CI suite.

The release workflow reuses this CI workflow at the exact validated tag commit, including the PHP matrix and runtime-enabled Plugin Check. It scans Git history, current source and the extracted runtime package with pinned Gitleaks before preparing a draft. See [release distribution](release-distribution.md) for actual local results and workflow limits.

## WordPress 7.1 Plugin Check verification

GitHub release run [38608488827](https://github.com/hasancse06/QuixDevs-ProductClock/actions/runs/38608488827) checked the extracted runtime ZIP and failed only `outdated_tested_upto_header`: `Tested up to: 6.8 < 7.1`. Its PHP 7.4/8.2/8.4 integration jobs and standards/package jobs passed. The draft job was correctly skipped. The fixed commit has not run remotely yet.

Before changing the header, the existing suite was run locally on **PHP 8.4.13 / MySQL 8.0.46** with both additional combinations:

| WordPress | WooCommerce | Integration result |
| --- | --- | --- |
| 7.1.3 | 10.0.4 | 41 tests, 78 assertions, passed |
| 7.1.3 | 11.2.1 | 41 tests, 78 assertions, passed |

Official version/plugin APIs confirmed WordPress 7.1.3, WooCommerce 11.2.1 and Plugin Check 2.1.0, matching the action's current dependency resolution. The fixed ZIP installed and activated in a separate disposable WordPress 7.1.3 / WooCommerce 11.2.1 store. Real queue publication/expiration, activity, price retention, native dashboard and Product Data controls passed smoke checks.

The `Tested up to` header is now **7.1**; minimum requirements and plugin version remain unchanged. Runtime-enabled Plugin Check, including experimental checks, passed against the absolute installed runtime-package path. Its live `api.wordpress.org` version lookup was enabled. The earlier isolated setup blocked external HTTP, which prevented this specific current-version lookup; the new reproduction permits the official API and reproduced the exact error before the fix.

The unmodified official action result processor returned **1** for the original finding and **0** for the fixed clean results. Packaging tests, PHPCS, syntax/integrity checks and workflow validation also passed. No check was excluded or suppressed. The workflows were already correct and were left unchanged. This is evidence for the combinations above, not every combination of newer WordPress, WooCommerce, PHP or extensions. [Release/tag rerun instructions](release-distribution.md#rerunning-after-the-plugin-check-fix).
