# Store operation guide

## Editing and manual overrides

Change dates and save to replace pending jobs. Disable scheduling and save to cancel pending work. Unchanged completed dates stay completed; enter new dates for a new lifecycle. Future publication requires a draft. Expire Only requires a published product; ProductClock never silently drafts a product to prepare a launch.

Manually changing product status, including trash/restoration, suspends automation. After reviewing status and dates, check **Resume this schedule** in Product Data → ProductClock and save. Completed transitions remain completed. Private, pending-review, future and trashed statuses are never automatically changed. Unsupported product types suspend at execution.

Past dates queue work for the next available worker. If expiration has passed, publication is suppressed; a published product returns to draft and an unpublished product remains draft. Equal dates and expiration before publication are rejected.

## Timezones

New schedules use the WordPress site timezone by default; administrators can choose UTC for new schedules. Existing schedules keep their captured timezone and UTC timestamps. A site timezone change does not move their execution instants. There is no per-record timezone reassignment UI.

Choose a named timezone in WordPress settings for daylight saving rules. Nonexistent spring-forward times and repeated fall-back times are rejected: select another valid time or create a new UTC schedule. See [timezone details](timezones.md).

## Permissions, settings and activity

Shop managers with `manage_woocommerce` can view ProductClock screens and configure products they are authorized to edit. Global settings and recovery tools also require `manage_options`. Tools require a confirmed, nonce-protected POST.

Settings control global pause, publication, expiration, logging, timezone for new schedules and opt-in uninstall deletion. Pausing retains configuration; re-enabling queues reconciliation. Activity keeps up to twenty events per product for thirty days. Full logs use WooCommerce source `quixdevs-productclock` and follow native WooCommerce retention.

## Cron and recovery

Execution occurs at or after the due instant when an Action Scheduler worker is available. Traffic-driven WP-Cron cannot guarantee exact-second execution. Monitor **WooCommerce → Status → Scheduled Actions**, filtering group `quixdevs-productclock`.

Ask your host to run an appropriate PHP/WP-CLI command every minute. Replace these paths:

```cron
* * * * * /usr/local/bin/wp --path=/path/to/wordpress cron event run --due-now >/dev/null 2>&1
```

Verify the command and permissions before setting `define( 'DISABLE_WP_CRON', true );` in `wp-config.php`. Use the site's PHP executable and hosting configuration.

**Late task:** check server cron, queue failures and pause switches. Use Tools → Repair missing scheduled actions, then inspect the queue. Hourly recovery processes enrolled schedules in batches of fifty; it does not automatically resume suspended/error schedules.

**Failed task:** Activity shows a sanitized result. Inspect native WooCommerce logs, fix the cause and review the product before explicitly resuming. Interrupted status writes with a saved transition intent can complete bookkeeping during recovery.

**Recovery tools:** Run due tasks processes at most 25 pending unclaimed entries through the normal processor. Reconcile and repair enqueue recovery work. These tools can affect multiple due products; review the confirmation before use.

## Deactivation, uninstall and multisite

Deactivation cancels owned pending tasks and keeps product statuses and schedule data. An already-started write may finish. Reactivation reconstructs valid work. Uninstall preserves data unless deletion was explicitly enabled; native WooCommerce log files follow native retention.

Activate separately per store. Network activation is rejected and network-wide cleanup is unsupported. Multisite lifecycle remains unverified. See [testing limits](testing.md).

## Developer verification

Never run tests or the test database installer against a store database. The integration framework resets its tables. Follow [the testing guide](testing.md) with a disposable database.
