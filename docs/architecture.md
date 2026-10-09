# Architecture

ProductClock is a new, independent 1.0.0 implementation. It uses no unrelated plugin data or business behavior.

The bootstrap registers a narrowly scoped PSR-4 loader and lifecycle hooks. `Plugin` wires explicit dependencies once after plugins_loaded. Runtime dependency checks precede construction. Scheduler API calls occur after Action Scheduler initialization, at init priority 20, or in later admin/worker callbacks. No extra Action Scheduler copy is bundled; customers do not need Composer.

`Schedule_Manager` validates and commits revisions. `Schedule_Repository` owns the atomic aggregate and three small derived query keys. `Queue` creates/cancels individual tasks. `Transition_Processor` validates due work and applies only draft/publish status changes using WooCommerce CRUD. `Status_Guard` serializes WordPress product writes before SQL. `Reconciliation` visits enrolled records using a keyset cursor, fifty at a time, and repairs missing tasks. State derivation and completion flags prevent repeated work.

`Date_Time` strictly parses wall times, rejects DST gaps/folds, and displays stored instants in the saved zone. `Validator`, `Settings`, `Logger` and `Lock` each have one concern. Admin components use native WooCommerce Product Data and wp-admin forms, with assets restricted to product editing.

## Concurrency and persistence

A connection-scoped MySQL/MariaDB advisory mutex serializes schedule editing, execution, recovery and supported external WordPress status writes for each enrolled product. Database disconnect automatically releases it; no stale lease can be stolen during a long callback. A three-second lock timeout fails closed. SQL is used only for this primitive, bounded keyset selection and aggregate admin counts; no custom tables are created.

The canonical schedule is one serialized postmeta value to avoid partially observed revisions across ten separate metadata writes. UTC times, revision, completion flags, expected status and transition intent commit together. Derived keys may be temporarily stale after a process crash; recovery rebuilds them from the aggregate. The system does not claim a transaction spanning arbitrary WooCommerce extension callbacks. Direct SQL status changes bypass WordPress hooks; an unexpected status is detected and suspended before the next transition, but an undetectable change-away-and-back cannot be inferred.

Completion is committed before extension actions fire. External hook side effects cannot be guaranteed exactly once across process crashes. Integrations must be idempotent and should use product ID plus revision as their deduplication key.

## Performance and scope

Normal frontend requests register lightweight callbacks; they do not scan products or load assets. Full service classes are small and shared with worker requests; no catalog query runs on ordinary frontend traffic. Recovery is hourly, using only enrolled records. Admin pages use twenty products per page; activity is bounded to twenty events per product. Core postmeta indexes are used without schema changes; very large enrolled catalogs should be measured in staging because WordPress metadata sorting/counts still cost database work.

Only the classic product editor is implemented. The beta editor is not targeted; the official retirement advisory is linked in README. Network activation is rejected; each store is administered separately.
