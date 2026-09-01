# Incremental implementation roadmap

Status: **Phase 4 user and administration dashboards completed and verified on 2026-09-01**. See the [Phase 4 report](phases/phase-04.md). Later phases remain gated by explicit scope requests.

## 1. Phase gates

The user must review the architecture and normalized schema before Phase 1. Review approval authorizes only the agreed phase scope; it does not authorize building every module. After each implementation phase, stop at its verified checkpoint and report results before moving to the next requested phase.

For every implementation phase:

1. Inspect the current repository, instructions, Git changes, installed versions, migrations and previous phase report. Preserve unrelated changes.
2. Write a short scope-specific plan and acceptance criteria. Resolve assumptions against the approved architecture.
3. Implement only that scope: migrations, backend actions, queries, policies, validation, translations and UI.
4. Apply required migrations to the intended local development database after verifying its identity; use a separate disposable test database. Never reset a user's database to get tests passing.
5. Run automated tests covering behavior, failure paths, authorization and meaningful invariants, plus the production frontend build and formatting checks. Fake external services in routine tests.
6. Start the application locally; start queue worker/scheduler where relevant. Record the actual URL, not an assumed running address.
7. Use the browser to exercise successful and denied flows. Inspect console/network/server errors. Verify desktop, laptop, tablet and mobile, in English LTR and Arabic RTL.
8. Fix defects, rerun affected tests and repeat browser verification. If an external dependency blocks verification, clearly mark the phase incomplete instead of claiming success.
9. Update the phase report with changes, schema impact, exact commands/results, browser evidence, known limits and unresolved risks.
10. Create a local Git checkpoint for the phase after verification. Stage only intended changes; never push or deploy as part of a local checkpoint.

Suggested viewport coverage: desktop 1440×900, laptop 1280×800, tablet 768×1024, mobile 390×844, with a narrow 360px spot-check. Check keyboard/focus behavior, labels/errors, modal and drawer use, touch targets, overflow, long translations, loading/empty/error states and readable RTL content. Repeat critical flows in both directions, not merely screenshots of the home page. Viewport emulation is not a claim of physical-device testing.

The baseline command set is `composer validate --strict`, `composer check-platform-reqs`, `composer lint`, `php artisan test`, `npm run build`, and `npm run test:browser`. Run migration checks against MySQL, including forward migration and disposable-database rollback/reapply where meaningful. Check `composer audit` and `npm audit` during dependency changes and release hardening. Record actual outcomes in the phase report.

## 2. Phases and acceptance criteria

| Phase | Requested-scope proposal | Database work | Acceptance boundary |
| --- | --- | --- | --- |
| **0 — Architecture review (completed)** | Inspect environment, propose architecture/schema/packages/security/roadmap, write AGENTS.md | None | Reviewable documentation; no application code, packages, migrations, local web server or UI testing |
| **1 — Laravel foundation** | Laravel 13 skeleton, Livewire/Tailwind build, configuration, environment examples, English/Arabic locale and direction support, reusable responsive layout primitives, local setup README and baseline test tooling | Only required framework user/session/reset/cache/queue tables; no speculative module tables | MySQL connection and fresh test DB verified; localized foundation page runs at recorded URL; assets build; responsive English/Arabic shell passes browser checks; sensitive routes not exposed |
| **2 — Authentication (completed)** | Email/username-password registration/login/logout, reset/forgot password, email verification, pending/active/disabled state, session list/revoke, Google and SMS OTP, shared sign-in pipeline, local status command | Extended users; social accounts; OTP challenges; scoped account audits | Verified with PHP and browser tests. RBAC/roles/admin shell were outside that phase and were completed in Phase 3. |
| **3 — User and role administration (completed)** | User search/create/edit/approval/disable/reactivate/delete; role creation/edit/assignment and permission catalog UI; real account/role dashboard counts | Spatie roles, permissions and three package pivot tables; reuse users and account audits | Verified: credential revocation on disable/role changes; no privilege escalation or self-grant; last-active-super protected with serialized checks; permission-protected responsive admin screens |
| **4 — User and administration dashboards (completed)** | Separate personalized portal/admin dashboards, permission-based responsive navigation, real user/account-audit summaries, and explicit unavailable states for future modules | None; one catalog permission added through the existing idempotent seeder | Verified English/Arabic LTR/RTL layouts at desktop, laptop, tablet, mobile and 360px; server authorization and navigation filtering tested; no fabricated business data or dead links |
| **5 — Prompt library and personal prompts** | Categories, tags, search/filter, prompt detail, favorites, copy, private personal CRUD/organization, admin prompt CRUD/publication/archive/audience controls; dashboard prompt summaries | Prompt/category/translation, grants, tag, favorite and use tables | Private/draft/selected/all visibility tested for owner, allowed user, allowed role, unrelated user and admin; search/counts/favorites do not leak; stale-edit and duplicate-favorite behavior tested. AI-launch buttons wait until Phase 7 |
| **6 — Private documents and brand guidelines** | Shared private storage, quarantine/scan, bounded PDF/DOCX/text extraction, image upload readiness status, guideline creation/versioning/activation/download, active context query interface | stored_files, document_extractions/chunks, guidelines/versions/activations | Unauthorized file paths/previews denied; unsafe/oversized/fake MIME files rejected; failed scan cannot activate; concurrent activation remains consistent; versions immutable; Arabic sample extraction evaluated and limitations visible |
| **7 — AI Assistant** | Model settings and role limits, new/send/continue/rename/delete conversations, queued execution, prompt launch, guideline context, request statuses, usage facts, real dashboard AI history | AI model/config/access/limits, conversations/messages/requests/attempts/context/usage tables; prompt-use AI FK | Ownership and role/model limits enforced server-side; timeout/429/failure handling and duplicate submissions tested; quotas atomic; exact context versions recorded; deletion suppresses late writes; fake service tests plus controlled real OpenAI smoke test |
| **8 — Event core and calendar** | Event categories, CRUD/status/cancel/duplicate, audiences, attendees/managers, monthly/weekly/list calendar and details workspace shell; dashboard event summaries | events/categories, audience/attendance/manager pivots; event activity for core actions | Range/timezone/multi-day rendering checked; cross-user events excluded; manager scope enforced; duplicates start private drafts; invalid intervals/transitions rejected; all three views and mobile list verified |
| **9 — Event files, photos and reports** | Multi-photo uploads/gallery/preview/download/delete/order, event collections, documents/designs, pre/post-report uploads/version history, event file activity and recent-upload dashboard | event_files, event_reports, event_report_versions; image derivatives only if needed | Permissions and parent ownership checked for upload/download/thumbnail/delete; report type/version consistency tested under concurrent uploads; scan rejection visible; responsive upload/gallery/report flows verified |
| **10 — Event communications** | Arabic/English internal email, LinkedIn and general copy drafts, manual edits, saved versions, AI generation/regeneration and copy; activity records | event_communications and immutable revisions; nullable AI request communication-target FK | Six event/type/language combinations isolated correctly; AI respects event access and guideline context; regeneration does not overwrite current edits; stale saves rejected; no unintended external sending/publishing |
| **11 — Search, notifications and analytics** | Cross-module authorized search, in-app notification center, event/publication/upload updates and reminder scheduling, full real dashboard, administration analytics | notifications/delivery deduplication; only justified settings/indexes/rollups | Search/snippets/counts and notifications never leak revoked access; reminders avoid duplicates and cancelled events; analytics match fixtures and defined time windows; failed queues recover observably; Arabic queries exercised |
| **12 — API and release hardening** | Versioned REST endpoints for completed capabilities when approved, API Resources and Sanctum auth; security/accessibility/performance review, operations/runbooks/backup restore, production readiness | Sanctum tokens and proven tuning indexes, no speculative mobile schema | API cannot bypass web policies; tokens expire/revoke and disabled users lose access; MySQL migration path and backup restoration verified; workers/scheduler/storage/HTTPS configured and monitored; all module regression/browser checks pass |

Phase 12 prepares the Laravel backend for Flutter; it does not build a Flutter app. Deployment is a separate explicitly requested operation after a concrete release candidate exists.

## 3. Phase 1 detailed boundary for review

Deliver only:

- A Laravel skeleton merged into this existing repository without overwriting its Git metadata or planning files.
- Stable dependency constraints and lockfiles, PHP/Node engine checks, `.env.example`, safe ignores and a local setup README.
- A dedicated MySQL development database and isolated test database using confirmed credentials and configuration; baseline framework migrations only.
- Livewire/Blade/Tailwind asset wiring, localized English/Arabic foundation page and reusable layout/navigation primitives; no fake dashboard metrics, login functionality or module CRUD.
- Locale allowlist, locale persistence for the foundation page, `lang`/`dir`, logical layout spacing and basic accessible mobile navigation.
- PHPUnit feature smoke tests, frontend build and a repeatable browser smoke flow at documented viewports.
- Local startup and verification report, then a local Git checkpoint.

Do not install all optional packages, create all target schema tables, build unapproved business modules, call OpenAI, or provision production infrastructure early. Fortify arrived in Phase 2 and RBAC in Phase 3. The target schema remains incremental so completed phases do not force speculative tables.

## 4. Integration prerequisites and honest completion

| Needed by | Inputs / infrastructure | Behavior while unavailable |
| --- | --- | --- |
| Phase 1 | Safe local MySQL startup/credentials, reachable package registries, explicit Node/PHP paths | Report environment blockers; do not silently substitute SQLite for required MySQL verification |
| Phase 2 | Mail transport/test inbox and administrator bootstrap identity | Mail fakes can test logic; real verification/reset delivery remains unverified until smoke-tested; bootstrap via secure one-time path, not committed credentials |
| Phase 2 provider smoke checks | Google client/callback and allowed account policy; selected SMS provider, sender and test number | Test doubles are permitted for development; do not claim real OAuth/SMS functionality has been verified |
| Phase 6 | Scanner, bounded extraction tools, representative Arabic/English documents, upload limits and image processing runtime | Unscanned files stay quarantined; extraction/OCR limitations are explicit; no production scanner bypass |
| Phase 7 | OpenAI project key in server environment, allowed model IDs, quotas and approved internal-data policy | Test with HTTP fakes; real generation remains unverified without a safe authenticated smoke test |
| Phase 12 | Hosting, secret storage, production MySQL/workers/scheduler/private storage, retention/backup/monitoring choices | A deployable candidate is not a production deployment; document release blockers |

Ask for missing integration inputs when needed, after completing independent in-scope work. Never print tokens or request that secrets be committed. No paid integration or provider account is created during planning.

## 5. Phase report template

Each implemented phase gets `docs/phases/phase-NN.md` containing:

- Approved scope and acceptance criteria.
- Current runtime/package versions and starting Git revision.
- Completed backend/UI/security/localization work and migration notes.
- Commands run, exit/result summaries, database identity (no password), local URL and service status.
- Browser flows checked, viewport/language matrix and evidence file paths where captured.
- Errors discovered and fixed, failed checks, external-service fake versus real verification, remaining limitations.
- Checkpoint commit reference, recorded in the final response or a subsequent report if the report is part of that commit.

Do not mark a phase complete because its code compiles, a screenshot looks correct, or fake-provider tests pass while required live verification is blocked. The current planning phase has no executable application; application tests, migrations, server startup and browser/responsive tests are **not applicable**, not passed.
