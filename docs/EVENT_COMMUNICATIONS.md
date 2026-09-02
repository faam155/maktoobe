# Event communications

Phase 15 enables Communications in the administration and user event workspaces. Each event has up to six stable slots: `internal_email`, `linkedin_post`, `general_copy`, each in `ar` and `en`. Slots are created only when saved or used for an AI request. Title represents an email subject or copy heading. Status is manually recorded as draft, ready, approved or used; none of these states sends or publishes anything.

## Authorization and revisions

EventCommunicationPolicy requires active, verified users and current EventAccess for viewing. `manage-events` additionally permits editing, archival and revision history; `use-ai` is also required for generation and applying suggestions. Existing event management semantics apply, including manager access to private events. Being an attendee, organizer or uploader alone does not grant communication editing.

Event/type/language uniqueness isolates the six slots. Each manual save, applied AI suggestion and archive appends an immutable revision with title, content, status, origin, editor and timestamp. Current content remains on the logical communication row for efficient reads. Save and archive compare the submitted revision number under the event row lock. Stale writes fail without changing data. Inputs never determine event ownership or author identity. History is paginated at ten revisions. Archive hides content from viewers, retains history, cancels pending generations and can be reversed by explicitly saving another revision.

## AI boundary

`GenerateEventCommunication` validates the operation, target slot, model, revision and instructions before creating a queued `event_communication_generations` record. This dedicated request ledger reuses AiProvider, AiModelAccess, the `ai` queue and BrandGuidelineContext, rather than fabricating an owner-only Assistant conversation for shared event content. It records exact source slot/revision, model/settings, requested user, timestamps, status, provider ID and returned token counts. Input snapshots and suggestion results are encrypted and hidden from generic serialization.

Generate/regenerate use event context and current saved copy. Improve requires current saved content. Translate explicitly uses saved, non-archived content from the other language of the same type and event. Context includes bounded event title/description, UTC dates, timezone, location and optional instructions, never event documents, photos, reports, attendees or unrelated conversations. The provider is asked for title/content JSON; bounded plain-text responses are also accepted with the event title as a heading, allowing existing provider adapters. Output is rendered as escaped text, never executable HTML.

Brand use is optional. Selection requires the active, clean, extraction-ready version and saves that exact version FK. The worker uses that version even if activation changes, and fails closed if its usable context is revoked. Phase 10's bounded text-only extraction limitation still applies; no new extraction/RAG capability is introduced.

Generation jobs carry only an ID, dispatch after commit, claim work under a lock and make at most one provider attempt. Active/verified status, event permissions and model availability are rechecked before the call and before retaining a late result. Archive/deleted events or revoked access cancel processing. Failures retain safe codes, not raw provider messages. Timeouts do not trigger automatic paid retries; a new explicit request uses a new operation UUID. Per-user idempotency prevents duplicate work, and limits allow ten new requests/minute with at most five queued/processing requests. Output is capped at 20,000 characters and the configured output budget is clamped to 64–4,000 tokens. There is no monetary quota/billing system.

AI suggestions belong to the requesting editor, who sees only the latest five for the selected slot. They do not update saved content automatically. Apply checks event, requester, completion, unapplied state and exact base revision under the same lock before appending a draft revision. Applying twice or applying after another edit is rejected. Other event managers can see saved revisions but cannot inspect another editor's unused suggestions.

## UI and operations

Blade provides manual forms, type/language navigation, status, copy, archive, revision history, AI selection, Brand opt-in, errors and explicit apply. English/Arabic interface direction is independent of the selected content language. Mobile forms stack. Polling only checks request status and offers a refresh link; it never reloads over unsaved edits. Save edits before requesting AI. Copy has a fallback when browser Clipboard permissions are unavailable.

Normal environments use the existing database queue: run `php artisan queue:work --queue=ai --tries=1` with the configured timeouts/retry window. Browser verification uses the existing sync queue and visibly labelled deterministic local provider. Feature tests mock the provider, and a separate database queue test verifies real after-commit dispatch/worker behavior. No paid API call, email delivery, LinkedIn integration, analytics, API endpoint or extra package is introduced.

Physical/remote provider retention remains a separate policy. Archive is not erasure. AI-generated language quality and real provider delivery are not proven by mock tests; editors must review suggestions.
