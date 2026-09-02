# Phase 16 — Notification System

## Scope and baseline

Started from Phase 15 checkpoint 4acf84f after reviewing AGENTS.md, existing Users/Events/Prompts, domain actions, audience services, migrations, queue conventions and the Phase 15 report. Existing Laravel 13 / Livewire 4 architecture and packages were retained; no new dependencies. The existing verified application had 180 PHP tests and 93 browser tests.

Plan: add a Laravel database inbox with durable duplicate-safe delivery, connect current event/prompt/report actions, schedule 24-hour reminders, implement bilingual panel/inbox/system notices, verify current authorization and all regressions. No external channels or future modules.

## Implemented

- Event publication/update/cancellation, explicit user/organizer assignment, prompt publication, report upload and system notices.
- Database-backed notification indicator and five-entry panel in both layouts; paginated inbox, unread filter/count, individual/all read, dismissal and authorized related-resource navigation.
- Current audience checks at both delivery and display, including revoked roles/grants, withdrawn reports, inaccessible prompts and inactive users. Resource content is not copied into delivery payloads.
- A dedicated notifications queue with transactional batches/cursor and unique deliveries. Every-minute Laravel Scheduler command records one reminder per event/start instant within 24 hours and recovers pending work.
- Permission-protected, validated bilingual system notices with explicit recipient/broadcast confirmation, request throttling, recent-auth protection and redacted audit.
- English LTR / Arabic RTL layouts, mobile header wrapping and an anchored mobile panel. Existing authentication and AI delivery semantics remain unchanged.

Important files: NotificationController; Actions/Notifications; Services/Notifications/NoticeAccess; Queries/Notifications/NotificationInbox; DeliverWorkspaceNotice; WorkspaceNotification and the two notification models; QueueWorkspaceNotifications; the migration; event/prompt/report action hooks; notification views/translations; shared layouts; NotificationTest, NotificationQueueTest and notifications.spec.js. See [NOTIFICATIONS.md](../NOTIFICATIONS.md) for operating and extension conventions.

## Database

Verified development identity maktoobe / maktoobe_app@127.0.0.1 before applying 2026_09_02_001400_create_workspace_notifications, batch 18. Adds workspace_notices and Laravel notifications with real foreign keys, operation/delivery uniqueness and recovery/inbox indexes. No existing data reset. Separate guarded MySQL test/browser databases exercise migration reset/rollback.

## Verification

Full PHP regression passed: 191 tests, 1,240 assertions in 175.95 seconds. This includes 11 new notification tests covering recipients, ownership, read/dismiss state, revocation, prompt/report hooks, batch resume, deduplication, reminder rescheduling and a real database-queue commit/rollback/worker test. The complete Chromium regression suite passed: 99 tests in 23.5 minutes. Desktop 1440x900, laptop 1280x800, tablet 768x1024, mobile 390x844 and narrow 360x800 checks include English LTR and Arabic RTL. Reviewed desktop English and mobile Arabic notification screenshots; no overflow or console errors. The full HTML evidence is retained in ignored .runtime/phase16-regression-report. A fresh targeted run also passed all six notification browser cases in 2.1 minutes, including keyboard reopening of the panel, invalid-recipient validation, read/dismiss operations, authorized event navigation and current private-event exclusion. Fresh screenshot evidence is in ignored test-results/notifications-*/notifications-*.png.

Discovered and corrected during verification: malformed initial system-action array caught by Pint before runtime; mobile header overflow caused by adding the notification control; obsolete dashboard assertions expecting the notification placeholder; a browser selector matching both existing event headings. The production Vite build, Composer validation, Pint and Git whitespace checks pass. No new application exception was found in final log inspection; retained entries predate Phase 16. Composer ran without its outside-workspace cache; validation succeeded.

Local preview remains on port 8000; unauthenticated notification access was inspected in the browser and redirected to sign-in. Functional browser mutations used the guarded database/server on port 8001. Started hidden dedicated notification worker (PID 26060) and scheduler (PID 33328); repeated scheduler runs succeeded and both dedicated error logs remained empty. Logs are ignored .runtime/phase16-worker.*.log and .runtime/phase16-scheduler.*.log. AI regression uses existing mocks/local adapters; no paid API calls or external notification sends occurred.

## Limits

Database channel only: no email, SMS, push, LinkedIn or other external delivery. No retrospective notification backfill. Counts refresh with page requests. Recipient eligibility is evaluated at delivery time, with an audience ID ceiling that excludes later registrations. A changed event start can legitimately create a new reminder. Dismissal retains deduplication records; erasure/retention remains a separate workflow. Production needs supervised workers, a host scheduler and failure monitoring. Browser viewport emulation is not physical-device verification. Stop after Phase 16; no push or deployment.
