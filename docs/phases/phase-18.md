# Phase 18 — Localization, Arabic RTL and English LTR

Completed and verified on 2026-09-07 from starting revision `958cb53`.

## Delivered scope

- Audited all implemented modules and preserved one shared page implementation for both locales.
- Made the language preference durable on authenticated accounts while retaining session-only guest behavior. The shared sign-in pipeline adopts an explicit guest choice for password, Google and OTP authentication.
- Standardized document direction through `App\Enums\Locale` in authentication, portal and administration layouts.
- Added recursive translation-catalog and static-reference checks, filled application validation rules/attributes in both languages, and localized remaining placeholders, file sizes, AI token counts, bootstrap errors and stale error-page copy.
- Mirrored back-navigation arrows in RTL and marked representative user-authored prompt, conversation, chat and event content with automatic direction without translating it.
- Added a cross-module Playwright audit for primary portal and administration pages in both languages, with overflow, raw-key, console and persistence checks.
- Isolated older browser viewport tests by resetting their fixture per case now that locale is intentionally durable.

No migrations, tables, columns, indexes, packages or external integrations changed in this phase. `php artisan migrate:status` confirmed all 19 existing migration batches are current on the development database.

## Verification evidence

- `vendor\\bin\\pint --dirty`: passed.
- `php artisan test`: 206 tests passed, 1,386 assertions.
- `npm run build`: Vite 7.3.6 production build passed.
- `npm run test:browser`: 108 Chromium tests passed. Coverage includes 1440×900, 1280×800, 768×1024, 390×844 and 360px layouts across the implemented module suites, English LTR and Arabic RTL, authorization and validation/error paths, and browser console monitoring.
- The new localization crawl visited the portal dashboard, prompt library, personal prompts, assistant/history, events, calendar, notifications, account security, administration dashboard, users, roles, permissions, categories, prompts, events, brand guidelines, analytics and audit logs at desktop/tablet/mobile sizes in both locales.
- The local application health endpoint returned HTTP 200 at `http://127.0.0.1:8000/up`. The existing signed-in portal was also opened in Chrome and its dashboard/event navigation inspected.
- Application logs contained no new entries during the phase; Playwright captured no page or console errors in the passing suite.

## Issues found and fixed

- Signed-in language changes previously lived only in the current session despite the existing `users.locale` field.
- Arabic requests could fall back to English for several framework validation rules.
- A few technical labels/placeholders and bootstrap validation messages remained literal English.
- Back arrows did not mirror in RTL, and mixed-direction user content lacked automatic direction hints in several high-use views.
- Persisted locale exposed shared-user state in four older viewport suites; fixture reset is now per test case.

## Remaining limitations

- User-generated content is intentionally not machine translated.
- Arabic/English document extraction, external email/SMS/push delivery and public REST/mobile APIs remain in their assigned future phases.
- Fonts and browser date rendering depend on the client platform, although layout direction and overflow are covered by Chromium regression tests.

