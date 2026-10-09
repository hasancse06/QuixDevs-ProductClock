# Scheduler and lifecycle

## Saving

With valid capabilities and nonce, lock the product, validate mode and strict local dates, preserve its configured timezone, convert to UTC, commit a new revision, cancel pending actions for the previous revision and queue unique one-time replacements. Committing the new revision before cancellation makes concurrently claimed old actions harmless. Unchanged schedules keep their revision and completed flags. Disabled schedules retain configuration and cancel pending work. Re-enabling unchanged dates preserves completed transitions.

A future launch on a published product is rejected with instructions to draft it manually. Expiry-only enrollment requires a published product. Unsupported product types/statuses are rejected. Saving does not itself change product status.

## Transition table

| Situation | Result |
| --- | --- |
| Draft, publish in future | Pending publication; task waits until due |
| Draft, publication due, expiry absent/future | Publish using WC_Product::set_status/save |
| Published, expiry future | Pending expiration |
| Published, expiry due | Return to draft |
| Draft, expiry already passed | Consume expiration without publishing |
| Both instants passed | Suppress publication; expiration wins |
| Expiry <= publication / missing required datetime | Reject save; previous configuration remains |
| Disabled/global pause/action-type pause | No status mutation |
| Manually changed status / trash / restore | Suspend and cancel; explicit resume required |
| Private/pending/future/unsupported product | Never transition; suspend when encountered |
| Deleted product | Cancel during deletion; remaining callbacks safely skip |
| Stale revision / duplicate / completed action | Skip without mutation |
| Interrupted after status write | Saved intent allows completion bookkeeping if current status matches the intended target |
| Exception during transition | Error state, sanitized diagnostic, canceled pending work; explicit resume after repair |

Publish-only completion uses `completed`; expiry completion uses `expired`. Published products awaiting expiry use `pending_expiration`, keeping scheduler state distinct from post status.

## Recovery

One hourly recurring action, `quixdevs_productclock_reconcile`, starts a chain of `quixdevs_productclock_reconcile_batch` jobs. Each visits up to fifty enrolled product IDs using keyset pagination. It repairs missing actions and query indexes, prunes activity, and incrementally checks fifty queue entries for obsolete revisions. Repeated runs use unique tasks and a product mutex. In-progress tasks are not duplicated. Suspended, error, disabled and completed work is never rearmed. Failures in individual recovery records are logged without aborting the batch.

Deactivation marks execution inactive so already-claimed callbacks that have not started skip safely, then cancels only plugin-owned pending hooks in group `quixdevs-productclock`. Metadata/statuses remain. An activation recovery flag reconstructs valid tasks on the next initialized request. Settings changes queue recovery. Global pauses leave configuration intact; missed jobs recover on resumption. No scheduler promises real-time execution; use server cron and inspect queue failures.

Tools → Run due tasks inspects at most twenty-five unclaimed due queue entries and uses the same processor/mutex. Completed callbacks may later consume their original Action Scheduler entries as harmless no-ops; manual execution does not impersonate a scheduler claim.

## Public extension hooks

| Action | Arguments |
| --- | --- |
| quixdevs_productclock_after_publish | product ID (int), revision (string), scheduled UTC timestamp (int), execution UTC timestamp (int) |
| quixdevs_productclock_after_expire | Same arguments |
| quixdevs_productclock_schedule_created | product ID, revision |
| quixdevs_productclock_schedule_updated | product ID, revision |
| quixdevs_productclock_schedule_failed | product ID, revision, action name, sanitized error code |

After-transition hooks run after a successful status change and committed completion. They do not fire for duplicate, already-at-target, skipped or recovered bookkeeping-only actions. A process interruption can prevent hook delivery; no exactly-once external delivery is promised. Do not call schedule mutation methods recursively from these hooks. Hooks are notifications, not authorization bypasses; no filter can bypass status or time validation.

Task hooks `quixdevs_productclock_publish` and `quixdevs_productclock_expire` receive product ID and revision and are infrastructure entry points, not public execution URLs.
