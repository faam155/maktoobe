# Event calendar conventions

Phase 12 adds `/app/calendar` and `/admin/calendar` without a new package or API. The shared `EventCalendarController` delegates to `EventCalendarQuery`; the admin endpoint requires `manage-events`. Authentication, active-account and verification middleware remain unchanged.

The query applies Phase 11 `EventAccess` before interval filtering, option discovery, counts or pagination. Organizer/category options therefore cannot disclose inaccessible or out-of-range events. Visibility filtering is restricted to event managers. Organizer and creator access to drafts remains the existing Phase 11 rule.

Dates are interpreted in the user's IANA timezone (UTC fallback), converted to UTC for overlap predicates, and displayed in that same timezone. Intervals are half-open: `starts_at < range_end AND ends_at > range_start`. Month includes complete Monday–Sunday weeks; week has seven days; agenda defaults to the anchor month. Optional inclusive from/through dates select an agenda of at most 62 days. Inputs are validated and dates bounded to supported years.

Results select calendar metadata only, eager-load category translations/organizers, and paginate at 100 events. Dense periods explicitly show the total and page controls; they are never silently truncated. Multi-day events appear on every overlapping local day, without appearing on an exclusive midnight end date. Below 768px both grid modes become a grouped agenda. English LTR and Arabic RTL share logical CSS rules.

The calendar uses existing event details and mutations. No drag/drop, recurrence, all-day semantics, external feed, notifications, new event status, or workspace module is introduced. A day view is intentionally omitted. Future timed-grid interactions can be reviewed separately; FullCalendar is not required for this phase.

Migration `2026_09_02_001000_index_event_calendar_ranges` adds end-time and category/start indexes. Its rollback preserves the category FK supporting index, which MySQL can replace when a covering composite index is created.
