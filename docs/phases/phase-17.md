# Phase 17 — Analytics and Audit Logs

## Baseline and plan

Reviewed AGENTS.md, architecture/schema documents, Phase 16 report, all implemented module models/actions, authorization scopes, migrations and lockfiles before changes. Started from a clean working tree at Phase 16 checkpoint a13225b. Retained Laravel 13.29.0, PHP 8.4.8, Livewire 4.4.3 and Spatie Permission 8.3.0; no packages added or upgraded.

Plan: add date-filtered authorized SQL analytics; unify sanitized existing audit facts with transactional hooks; add separately authorized read-only audit search; record observed non-secret configuration changes; verify calculations, revocation, safety, bilingual responsive layouts and all regressions. No future module or settings editor was authorized.

## Implemented

- Analytics cards for users/statuses, accessible published library prompts, owned personal prompts/favorites/AI requests/conversations/tokens, accessible events/statuses, clean photos, logical reports and saved communications.
- Top-ten prompt and category rankings using existing copy/AI facts, with small native meters and visible text counts rather than a new chart dependency.
- Account-local Today, 7/30 days, month-to-date and bounded custom date filters. Cohort/status semantics and private-workspace boundaries are visible in both languages.
- Durable sanitized audit ledger, original-timestamp/idempotent legacy import, transactional account mirrors and existing event activity hooks. Explicit visibility/permission-change facts added.
- Read-only audit viewer with actor/action/resource/date filters, search and pagination. Separate view-audit-logs plus module permissions; no mutation routes.
- Approved non-secret configuration fingerprints and hourly/deployment command checks. No credentials or setting values appear in audit payloads.
- Existing administration overview AI conversation counts now respect ownership and use-ai permission.

Important files: Actions/Audit/RecordAudit; Services/Audit/SafeAuditMetadata; Models/AuditLog; Queries/Analytics/AnalyticsQuery; Queries/Audit/AuditQuery; Support/Reporting/ReportPeriod; Admin/ReportingController; the two console commands; reporting views/translations; admin routes/navigation; existing identity/event/governance audit hooks; ReportingTest and reporting.spec.js. See [conventions](../ANALYTICS_AUDIT.md) for complete semantics and extension rules.

## Database and operations

Verified development identity maktoobe / maktoobe_app@127.0.0.1 before applying 2026_09_03_001500_create_audit_reporting_tables, batch 19. Adds audit_logs, audited_configurations, eight creation-date indexes and the additive view-audit-logs permission/default administrator grants. No user data reset. Actor FK uses SET NULL; resource identity deliberately has no polymorphic FK so deletion does not remove audit history. Guarded separate MySQL PHP/browser databases exercise migration reset and rollback/reapply.

Ran php artisan migrate --force, audit:import-legacy and audit:configuration successfully. Rechecked migrate:status and schedule:list: migrations current; notification minute schedule preserved and configuration check hourly. The initial configuration observation is a baseline, not a fabricated change.

Started hidden local preview on port 8000 (PID 45744); browser inspection verified protected analytics redirects to sign-in. Functional browser mutations use the guarded server/database on 8001. Confirmed no queued/reserved development jobs, signaled queue:restart and verified both old workers exited before launching updated AI (PID 46344) and notification (PID 46372) workers. Existing scheduler PID 33328 remains running.

## Verification

Full PHP regression passed: **201 tests, 1,306 assertions**, 319.02 seconds on the final rerun (the earlier full run passed 1,304 assertions before adding two translation checks). Ten new reporting tests cover calculation/ownership boundaries, local dates, invalid periods, token totals/missing usage, report-version deduplication, current audience revocation, disabled accounts, separate permissions, sensitive-data exclusion, rollback, import idempotency, configuration changes, filtering and pagination. Focused reporting run passed 10 tests / 64 assertions.

Composer validation, platform requirements, Pint and Git whitespace checks pass. Production Vite build and Blade view compilation passed. The complete Chromium regression passed: **105 tests in 38.7 minutes**. This includes all prior modules plus six reporting cases covering 1440x900, 1280x800, 768x1024, 390x844 and 360x800 layouts, English LTR and Arabic RTL, analytics/audit filters, invalid custom ranges and ordinary-user denial. Reviewed English desktop analytics and Arabic desktop/mobile analytics/audit screenshots; filters, count cards, rankings, metadata disclosure and wrapping were readable without horizontal overflow. Browser console/page-error assertions were empty. The ignored HTML evidence is retained at `.runtime/phase17-regression-report`.

A read-only development-data profile of the analytics Query returned 17 metrics with 19 SQL queries (26.22 ms SQL, 76.5 ms total). This is a small local-data observation, not a production load benchmark; bounded indexes and current-access behavior are the implemented performance controls.

Verification fixes: corrected assertions for MySQL JSON key order and read-only route method responses; removed duplicate error rendering from new views because the shared administration layout already displays validation errors. Five focused bilingual viewport cases passed before the validation-selector failure exposed that duplicate. Added missing bilingual role-form labels for view-audit-logs and safe wrapping for long audit actor names during final review. Final preview, AI-worker, notification-worker and review-server error logs were empty; retained application log errors predate Phase 17.

## Limits

Private-workspace statistics remain actor-owned; this phase does not authorize a global private-data analytics bypass. Date counts are current-state cohorts, not historical snapshots. Missing provider token totals remain explicitly unknown. No paid AI calls are required by tests.

Audit immutability is enforced by the model/application, not against privileged direct SQL. Original legacy data is not purged; imports only sanitize the new ledger. Configuration sampling detects observed net changes, cannot identify the human editing files, and intentionally excludes secret rotations. Production retention, external archival, database privilege hardening and deployment remain separate work. Browser viewport emulation is not physical-device testing. Stop after Phase 17; no push or deployment.
