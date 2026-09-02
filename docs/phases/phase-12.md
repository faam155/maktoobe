# Phase 12 — Event Calendar

Status: completed and verified on 2026-09-02. Local checkpoint uses `Phase 12: Implement Event Calendar`.

## Review and scope

Reviewed AGENTS.md, Phase 11's report, event schema/access policies, existing portal/admin lists, translations and test conventions before changing code. Phase 11 checkpoint was `2d6cdd2`; the working tree was clean. The user explicitly replaced the earlier proposed Phase 12 files scope with the Event Calendar. File modules remain deferred.

Plan: reuse EventAccess and UTC event intervals; add one bounded calendar query and shared Blade view; supply desktop month/week and an agenda with mobile fallback; test audience protection, ranges, filters, pagination and bilingual layouts. No authentication changes or new packages were needed.

## Implementation and database

- Portal `/app/calendar` and permission-protected `/admin/calendar`, with period navigation, month/week/agenda selection, status/category/organizer filters and manager-only visibility filtering.
- Inclusive optional from/through dates use a maximum 62-day agenda. Queries use the account timezone and half-open UTC overlap, including multi-day events. Results and filter options are audience-scoped before retrieval.
- Calendar metadata is paginated at 100 events with a clear density notice. Responsive navigation and event links lead to the existing workspaces. Below 768px grid modes render a grouped agenda.
- Migration `2026_09_02_001000_index_event_calendar_ranges` adds `events.ends_at` and `(category_id, starts_at)` indexes. Applied as batch 14 to local `maktoobe`. Existing foreign keys and data are preserved; no tables added.
- Found and fixed MySQL's removal of the original category FK supporting index when the composite index covers it. Rollback now restores that index before dropping the composite. Full queue/infrastructure regressions exercise migration rollback/reapply on the disposable test database.

Important files: `app/Queries/Events/EventCalendarQuery.php`, `app/Http/Controllers/EventCalendarController.php`, `resources/views/events/calendar.blade.php`, `lang/{en,ar}/calendar.php`, calendar migration, portal/admin routes/navigation, `resources/css/app.css`, `tests/Feature/EventCalendarTest.php`, `tests/Browser/calendar.spec.js`, and guarded browser fixtures. See [calendar conventions](../EVENT_CALENDAR.md).

## Verification

- PHP calendar tests: 6 passed, 33 assertions.
- Full PHP regression after the rollback fix: 143 passed, 866 assertions, 81.77 seconds, isolated `maktoobe_test`.
- Initial calendar browser checks: 5 passed, 1.9 minutes. Month/week/agenda, filters, private-event exclusion, detail navigation, console errors and overflow checked at 1440×900, 1280×800, 768×1024, 390×844 and 360×800 in English/Arabic.
- Full browser regression including the administrator/private-event/range-validation test: **75 passed in 12.5 minutes** on isolated `maktoobe_browser`.
- Visual review of generated desktop English, tablet Arabic and mobile Arabic screenshots confirmed grid/agenda readability and correct direction. Evidence: ignored `test-results/calendar-*/calendar-en.png` and `calendar-ar.png`. Calendar tests include empty filters, custom ranges, detail navigation and private-data exclusion; the administrator check confirms private filtering and oversized-range validation.
- `composer validate --strict`, `composer check-platform-reqs`, Pint and Vite production build passed; migration status confirms batch 14 applied.
- Existing manual preview at `http://127.0.0.1:8000/app/calendar` opens sign-in for guests; preview console has no errors. Automated browser fixtures use only `maktoobe_browser` on port 8001.
- No new application log errors were produced. `git diff --check` and calendar route registration checks passed.

## Limits

No day view, timed drag/drop grid, recurrence, external calendar feed, event files, attendance or notifications. These were not required for the approved scope. Dense calendars require pagination or narrower filters; device checks use browser viewport emulation, not physical devices. No push or deployment is included.
