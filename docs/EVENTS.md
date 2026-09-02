# Event core conventions

Phase 11 is the core event increment, not the calendar or file-workspace increment. It reuses `manage-events`; no package or authentication behavior changed.

## Data and time

`events` owns its title, description, category, organizer, UTC start/end instants, IANA timezone, lifecycle status, audience mode, creator/updater and soft-delete marker. Dates and times are submitted together through accessible local datetime inputs and converted to UTC in `SaveEvent`. MySQL enforces end after start. `DATETIME` avoids the 2038 range limit of MySQL `TIMESTAMP`.

`event_categories` has stable slugs, display order and active state; English and Arabic names live in normalized `event_category_translations`. The idempotent seeder creates Conference, Workshop, Meeting, Training and Community. Category administration is not included in this phase.

`event_user_access` and `event_role_access` are audience grants with composite primary keys, reverse indexes and grant attribution. They do not assign global user roles or grant management permissions. They are not attendance records. Only the pivot matching the selected visibility is retained when an event is saved.

## Authorization

All protected routes require authentication, an active account and verified email. Administration additionally requires `manage-events`; this scoped route group is accessible to Event Managers without granting them the administration dashboard or user administration. Mutations require recent authentication and call independently authorized Actions.

`EventAccess` is the common collection/detail/dashboard audience boundary. An explicit `manage-events` capability grants event-wide management and viewing. Other users can read events they organize or created, including drafts. Otherwise, drafts are hidden and the all-users, selected-users or current-role audience must match. Private events never become visible through an unrelated role name. Read/organizer access alone never grants mutation rights.

Search, pagination and portal dashboard counts apply the audience scope first. Creator/updater IDs come from the authenticated actor. Organizer and audience IDs are validated against active users and web-guard roles. Deactivated/deleted users remain governed by the existing account middleware.

## Lifecycle and mutation boundaries

New events start as draft, planned or confirmed. `ChangeEventStatus` permits draft → planned/confirmed, planned → confirmed, confirmed → in progress, in progress → completed, and cancellation of any nonterminal status. Completed/cancelled events cannot reopen through a generic form. Content edits cannot bypass the status action. Event saves and transitions use transactions and row locks.

Duplicate produces a new private draft, sets the actor as creator/updater/organizer, generates a distinct bounded slug and does not copy audience grants. Delete is a soft delete; there is no restoration or physical purge UI yet. `event_activities` stores redacted creation/update/status/cancellation/duplication/deletion facts without private descriptions. The activity UI remains unavailable until requested.

## UI and future work

Administration provides list/search/status filter, creation, editing, overview, audience details and lifecycle controls. The portal provides authorized search, upcoming/past/all lists, pagination and an overview workspace. English LTR and Arabic RTL use the existing responsive layouts and compact navigation.

Phase 13 enables Photos and Documents; see [event-file conventions](EVENT_FILES.md). Phase 14 enables Reports; see [report conventions](EVENT_REPORTS.md). Phase 15 enables Communications with reviewed AI suggestions; see [communication conventions](EVENT_COMMUNICATIONS.md). Activity remains a disabled tab. Attendance responses, per-event manager delegation, notifications and external communication delivery remain deferred.

Phase 12 now supplies authorized month/week/agenda views; see [calendar conventions](EVENT_CALENDAR.md). The activity tab remains deferred.
