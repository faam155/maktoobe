# Notification conventions — Phase 16

## Boundaries

Only Laravel's database channel is enabled. WorkspaceNotification is a Laravel Notification; User's notifications relation uses WorkspaceDatabaseNotification to add concrete user and notice foreign keys when the database channel creates a row. Existing authentication mail/SMS notifications are unchanged. No email, SMS, push or social delivery is implied by an in-app notice.

RecordWorkspaceNotice is an internal domain hook, never a request-array endpoint. Event save/status actions, prompt publication and successful report upload record a notice inside the existing domain transaction. Jobs dispatch after commit. SendSystemNotice validates bilingual text, requires manage-system-settings and an active verified actor, records a redacted audit, and supports an explicit recipient or confirmed broadcast. Its HTTP route also requires recent authentication and throttling.

## Storage and delivery

- workspace_notices stores a unique operation key, event/prompt/report-version references, optional target, occurrence time and durable delivery cursor. Only system notices store explicit bilingual text. Resource titles, private content, document paths and arbitrary links are not copied into notification JSON.
- notifications is Laravel's UUID/type/notifiable/data/read_at/timestamps table with notice_id, concrete user_id and dismissed_at. The fixed user morph alias is retained. Unique (user_id, notice_id) prevents duplicate delivery. Dismissal retains the row and unique key, so replay cannot resurrect it.
- DeliverWorkspaceNotice uses the notifications queue. It locks the notice, processes at most 50 existing user IDs, checks current access, calls notifyNow through Laravel's database channel and advances the cursor in the same transaction. The next batch dispatches after commit. Rollback cannot commit a cursor without its inbox entries.
- audience_ceiling captures the highest user ID when the notice is recorded; later registrations are not backfilled. Eligibility is evaluated at delivery time. Disabled, unverified and deleted users are excluded. A deleted direct recipient never becomes a broadcast because broadcast is a separate flag.
- Domain references restrict physical deletion; ordinary resource soft deletion makes notices inaccessible. Purge/retention must remove dependent records deliberately. Dismissal is not erasure.
- System operation IDs deduplicate repeated submissions. Events use their activity ID, uploads use the immutable report-version ID, and a prompt's actual transition into Published records one notice. Later unpublish/re-publish is a new publication.

## Read authorization

NotificationInbox scopes by authenticated recipient, dismissal and NoticeAccess before pagination or unread counts. NoticeAccess reuses EventAccess and PromptAccess. Drafts, inaccessible/deleted resources, withdrawn report versions and non-clean files are excluded. Report notices reference the exact version; valid previous versions remain historical notifications.

Read, dismiss and open routes use the same query and return 404 for foreign or revoked IDs. Mark-all updates only currently visible rows for that recipient. Related URLs are server-generated after authorization. Titles resolve from accessible models and Blade escapes all output. The panel shows at most five entries; the inbox paginates at 20. Counts refresh with page requests; there is no websocket/polling transport.

Assignment means explicit user audience grants or organizer changes. Phase 11 has no separate RSVP assignment workflow. Role audience recipients receive ordinary event updates through current access; this phase does not invent attendance.

## Reminder operations

The Scheduler runs notifications:dispatch every minute with withoutOverlapping. Only Planned/Confirmed events starting after now and within 24 hours qualify. One reminder is recorded per event/start instant. Repeated scheduler runs cannot duplicate it. Cancelling, starting or rescheduling removes an old reminder from the visible inbox; a new start may create a new reminder.

The same command requeues up to 100 incomplete notices to recover interrupted dispatch. Delivery remains idempotent if recovery overlaps queued jobs. Monitor worker failures and pending notice age; no monitoring integration is installed.

Development (separate terminals/process supervisor):

    php artisan queue:work --queue=notifications --tries=3 --sleep=1 --timeout=60
    php artisan schedule:work

Production: supervise the worker and invoke php artisan schedule:run once per minute from the host scheduler instead of schedule:work. The existing ai queue retains its separate single-attempt provider policy.

## Future channels

Future Laravel channels require explicit preferences, channel-specific idempotency and privacy review. Database atomicity cannot make external sending exactly once. Do not simply add external channels to notifyNow inside this transaction; introduce an outbox/delivery adapter with provider-specific retry semantics when approved.
