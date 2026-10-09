# Timezones

New schedules capture the WordPress site timezone (`wp_timezone_string`) or UTC as chosen in ProductClock settings. Existing records always use their captured timezone for editor display and parsing. Changing the site timezone or default preference does not rewrite existing UTC instants.

`DateTimeImmutable::createFromFormat` parses exact `Y-m-d H:i[:s]` or native `datetime-local` values. Calendar warnings, normalization, trailing content and timestamps before the Unix epoch are rejected. No server-timezone-dependent `strtotime` is used.

A wall-time round trip detects nonexistent DST times. Offsets from the timezone transition table are used to count possible instants for that wall time; a repeated fall-back time is rejected rather than silently choosing one. Choose an unambiguous minute. For a new UTC schedule, set the UTC default before enrollment. Version 1 does not provide per-record timezone reassignment; to preserve absolute scheduling safety, existing records remain bound to their initial zone.

Example: `2026-11-01 10:00` in `Asia/Dhaka` means `2026-11-01 04:00 UTC`. Native controls show local dates; human-readable displays use `wp_date` and the site's date/time format. The panel shows the zone ID and its current offset; the offset at a future scheduled instant can differ in DST regions, so the date display includes its applicable abbreviation.

Tests cover named/fixed-offset round trips, site timezone changes, calendar errors, New York spring gaps/fall folds, and valid times adjacent to transitions.
