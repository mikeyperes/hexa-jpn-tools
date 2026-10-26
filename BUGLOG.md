# Hexa JPN Tools bug log

## JPN-WP-2026-10-20-01 - Event time fixed to one city's zone on copied city sites (high)

- Symptom: a copied city site's WordPress timezone was Pacific, while the event manifest, date parser and calendars used Eastern.
- Cause: EventDates::TIMEZONE was used as a runtime value everywhere.
- Patch: resolve the existing WordPress site timezone consistently and preserve it during date maintenance. The standalone fallback outside WordPress is UTC (1.17.5).
- Guard: an isolated fixture checks Eastern and Pacific wall-clock parsing and the Pacific midnight cutoff.
