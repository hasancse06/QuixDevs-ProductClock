# Data model

All per-product keys begin `_quixdevs_productclock_`. No custom tables or large autoloaded options are used.

## Canonical schedule

`_quixdevs_productclock_schedule` is a single WordPress serialized array. This is a deliberate improvement over one key per field: readers under a mutex obtain a consistent revision, dates, intent and completion snapshot. The suggested independent field names become array members rather than redundant copies.

| Member | Type / meaning |
| --- | --- |
| schema | Integer 1; current record format |
| enabled | Boolean opt-in |
| mode | `publish`, `expire`, `both` |
| publish_at, expire_at | Integer Unix timestamps, UTC instants; 0 means unused |
| timezone | Valid DateTimeZone identifier or WordPress fixed offset, captured at enrollment |
| state | `disabled`, `pending_publication`, `pending_expiration`, `expired`, `completed`, `suspended`, `error` |
| revision | Unique UUID string replaced whenever the schedule is changed, disabled, resumed or suspended |
| expected_status | `draft` or `publish`, used to detect external overrides |
| publish_done, expire_done | Boolean completion markers, preserved for unchanged dates |
| intent | Empty string, `publish` or `expire`; persisted before the CRUD write to recover interruption |
| last_action | Empty string, `publish`, `expire` |
| last_execution | UTC integer timestamp; 0 means never |
| last_error | Empty string or a sanitized diagnostic code, never raw exception content |

The three derived query keys are `_quixdevs_productclock_enabled` (boolean serialized by WordPress as scalar metadata), `_quixdevs_productclock_state` (state string), and `_quixdevs_productclock_next` (UTC integer, 0 if no runnable work). They improve admin queries and are repaired by reconciliation. These keys are not authoritative for execution.

`_quixdevs_productclock_activity` contains up to twenty event arrays: `time`, `product_id`, `action`, `scheduled`, `result`. Old events are pruned after thirty days on recovery or activity reads. Native WooCommerce logging remains the primary diagnostic log and follows native retention settings.

## Options

`quixdevs_productclock_settings` is a small, non-autoloaded array: enabled, publishing, expiration, logging, remove_data, timezone (`site` or `UTC`). Other small, non-autoloaded options track activation state, recovery request, last reconciliation and queue cleanup cursor. User-specific tool messages are expiring transients.

Default uninstall behavior retains records and options. Opt-in removal deletes only ProductClock keys/options and its Action Scheduler group records. It never changes product status, price, stock or taxonomy data. Native logs are governed by WooCommerce retention.
