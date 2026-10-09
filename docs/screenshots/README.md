# Genuine ProductClock screenshots

Captured October 9, 2026 from the isolated disposable local WordPress admin installation: WordPress 6.8.3, WooCommerce 10.0.4, PHP 8.4.13. All products and store records are synthetic. No production store was used.

The available browser's desktop viewport was enlarged for the captures. The PNG exports crop browser/admin toolbar, usernames, local URLs and unused margins; they preserve genuine interface pixels without redrawing controls, changing CSS, resizing content or fabricating logs. The browser supplied JPEG pixels, then crops were saved as optimized PNGs. Four management captures are 1477 pixels wide. The product panel uses a tighter 1210-pixel crop for readable controls.

| Order | Capture | Contents |
| --- | --- | --- |
| 1 | [01-product-scheduling.png](01-product-scheduling.png) | Enabled Publish & Expire schedule, both dates, Asia/Dhaka timezone, pending publication and next action |
| 2 | [02-dashboard.png](02-dashboard.png) | Six managed demo products, pending/completed totals, upcoming transitions and health |
| 3 | [03-scheduled-products.png](03-scheduled-products.png) | Pending publication/expiration, completed, expired and suspended schedules |
| 4 | [04-settings.png](04-settings.png) | Actual global switches, logging, default timezone, retention and scheduler health |
| 5 | [05-activity.png](05-activity.png) | Genuine product_published/product_expired successes, reconciliation and manual override records |

“Launch Day Tote” was published and “Autumn Event Pass” expired by real ProductClock jobs processed through Action Scheduler. “Seasonal Preview” was manually moved to pending review and suspended by the plugin. History was produced by the plugin, not inserted as staged log text. Demo dates are illustrative; they are not a performance benchmark.

Activity is the fifth image because it shows real outcomes and recovery records. Engine diagnostics are on Dashboard, Settings and Tools; the captures do not imply a combined Activity/Diagnostics screen.

The matching [WordPress.org screenshots](../../wordpress-org-assets/README.md) are exact copies in this order. This repository includes only the five current numbered captures.

## Recreate manually

1. Use a disposable local WordPress/WooCommerce store with this unmodified plugin active, the classic editor, activity logging enabled and a named site timezone.
2. Create synthetic products: draft future combined schedules, a published future expiration, a completed publish-only product, an expired product and a manually suspended schedule.
3. Generate completed transitions through the real scheduler or confirmed Tools → Run due tasks. Never fabricate activity records.
4. Open Product Data → ProductClock with a saved combined schedule. Ensure the checkbox, both dates, timezone, status and next action are visible.
5. Capture Dashboard, Schedules with all states, Settings and Activity. Use Newest first on Activity to make recent outcomes easy to find.
6. Crop irrelevant context and identifiers without removing meaningful controls. Preserve original pixels and readable text; do not stretch or edit the interface.
7. Save the five numbered files, copy them to `wordpress-org-assets/screenshot-1.png` through `screenshot-5.png`, and verify matching captions and privacy before publication.
