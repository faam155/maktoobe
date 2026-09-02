# Phase 11 — Event Management Core

Status: **completed and verified on 2026-09-02**. Starting checkpoint: `48a1ade`. This report is part of the Phase 11 checkpoint; the final response records its commit hash.

## Scope and implementation

- Core events: title/description, bilingual categories, UTC interval with IANA timezone, location, organizer, status, visibility and actor attribution.
- Permission-protected create/edit/view/soft-delete, cancellation and validated lifecycle transitions, duplication as a private draft, user/role audience assignments.
- Shared collection visibility scope and policies; owner/organizer read access does not grant editing; management requires `manage-events`.
- Searchable, paginated administration and portal lists, real scoped event dashboard summaries, responsive navigation and overview-only workspace.
- English/Arabic views; future Photos, Documents, Reports, Communications and Activity tabs remain disabled.
- No new packages and no changes to authentication behavior.

Important files: `app/Actions/Events/*`, `app/Services/Events/EventAccess.php`, `app/Policies/EventPolicy.php`, the Event models/enums/factory/seeder, admin/portal Event controllers and views, `tests/Feature/EventCoreTest.php`, `tests/Browser/events.spec.js`, and the existing dashboard/navigation queries and views. Runtime remains Laravel 13.29.0 / PHP 8.4.8 with locked dependencies unchanged.

## Database

Migration `2026_09_02_000900_create_event_core_tables` creates `event_categories`, `event_category_translations`, `events`, `event_user_access`, `event_role_access` and `event_activities` with foreign keys and lookup/unique indexes. Migration `2026_09_02_000910_enforce_event_intervals` uses UTC DATETIME columns and a database end-after-start check. Applied to local `maktoobe` at `127.0.0.1:3307` as batches 12 and 13. Category seeding is idempotent and contains no events or credentials.

## Verification

- Full PHP regression: 137 passed, 833 assertions in 87.28 seconds against isolated `maktoobe_test`.
- Focused browser suite: 6 passed in 1.9 minutes against isolated `maktoobe_browser`; creation, status change, selected-user validation and access, denied administration, English/Arabic at 1440×900, 1280×800, 768×1024, 390×844 and 360×800.
- Full browser regression: 69 passed in 11.5 minutes. Final expanded admin/portal suite: 6 passed in 1.9 minutes, including all five viewports and English/Arabic screens.
- Screenshot review confirmed desktop English overview, desktop/mobile Arabic forms and mobile Arabic portal readability. Evidence is generated under ignored `test-results/events-*/event-overview-en.png`, `event-form-ar.png` and `event-portal-ar.png`.
- Composer validation/platform checks, Pint, production Vite build and `git diff --check` passed. All development migrations are current.
- Fixed an invalid test factory helper, updated obsolete foundation/dashboard unavailable-module expectations, and corrected the status dropdown's exact-label test locator to its accessible combobox role.
- Analytics-only event aggregates now use the same audience scope; a dedicated regression test protects private counts.
- Existing preview verified at `http://127.0.0.1:8000`; unauthenticated event navigation redirects to sign-in and the browser console is clear. No new application error log was produced during this verification.

## Limitations

- This phase follows the user's narrower core scope: no monthly/weekly calendar or attendance workflow.
- Category names are seeded in two languages; category CRUD is not included.
- Soft deletion is not erasure; restoration/retention/purge require later approval.
- No file upload, report, communication, notification or activity-feed UI is implemented.
