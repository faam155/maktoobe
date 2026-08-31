# Project guidance

## Current stage and authorization boundary

This repository is for an internal AI Prompt Hub and Event Management Platform built with Laravel. The user explicitly requires incremental development and review of architecture/database design before Phase 1.

**Current status: Phase 2 Authentication completed and verified on 2026-09-01.** Phase 1 is complete at `3205d33`. The latest user scope brought password, Google and SMS OTP authentication forward, overriding their earlier roadmap placement. Role administration, business modules and MFA implementation remain out of scope. Do not start another phase without explicit user scope.

Read the current conversation first: explicit user approvals and changes take precedence over this recorded status. Once the user approves the design/phase, update this status and proceed with that scope without asking for the same approval again. Never treat the full specification as authorization to implement all phases at once.

Design references:

- [Architecture, packages, security and folders](docs/ARCHITECTURE.md)
- [Normalized schema, constraints and lifecycle](docs/DATABASE.md)
- [Phase roadmap and verification requirements](docs/ROADMAP.md)
- [Observed local environment](docs/ENVIRONMENT.md)
- [Phase 1 implementation and verification](docs/phases/phase-01.md)
- [Implemented foundation conventions](docs/FOUNDATION.md)
- [Implemented identity conventions](docs/IDENTITY.md)
- [Phase 2 implementation and verification](docs/phases/phase-02.md)

These files are the working design baseline for the authorized foundation phase. Document approved changes rather than silently departing from them; later-phase specifics remain subject to their own scope review.

## Architecture rules

- Use a conventional Laravel modular monolith: PHP, MySQL, Eloquent, Blade, Livewire, Tailwind and Alpine as needed. Phase 1 locks Laravel 13, PHP 8.4, Livewire 4 and Tailwind 4; consult the phase report and lockfiles for patch versions and recheck compatibility before upgrades.
- One application/database/user population; separate `/app` and `/admin` route groups and layouts. Do not add a SPA, microservices, generic repository framework or Flutter application.
- Keep Livewire components/controllers thin. Shared Actions own nontrivial mutations/transactions; Queries own authorized lists/aggregates; Policies own reusable resource abilities; adapters isolate AI/SMS/extraction/storage integrations.
- Reuse these actions, policies and validation rules when versioned REST controllers/API Resources and Sanctum are introduced. Do not create speculative APIs or mobile code early.
- Use normal Laravel directories grouped by feature. Create classes/tables when their approved phase needs them, not the whole folder/schema tree in advance.
- Install packages only when needed and compatible; keep Composer/npm lockfiles committed. Never bypass platform constraints. Livewire supplies Alpine; avoid duplicate initialization.
- Phase 1's supported queue is the database connection with `after_commit=true`; batching/failures default to MySQL. Private local storage throws on write failure. The local environment uses daily info logs with 14-day retention. Follow FOUNDATION.md for worker, payload and logging rules; no business jobs or schedules exist yet.
- Phase 2 uses Fortify's selected password controllers behind application-owned routes/UI and one `CompleteSignIn` pipeline for password, Google and OTP. Socialite state must remain enabled. Production mail/SMS providers replace the fail-closed local adapters; local authentication inbox files are encrypted, private, environment-scoped and terminal-readable only.

## Data and authorization rules

- MySQL is the required application database and integration-test target. Use real FKs, unique constraints, indexes and deliberate CASCADE/RESTRICT/SET NULL rules; document package polymorphic exceptions. Test with a separate disposable database.
- Only run migrations after verifying the intended connection/database. Never run destructive refresh/reset operations on user data. Soft deletion is not erasure; physical file/content purge needs an explicit retention workflow.
- Users may have multiple roles; server-side capabilities plus ownership/audience checks enforce access. Hiding navigation is not authorization. Seed the five specified roles without hardcoding role names throughout business logic.
- Enforce active status and required verification on every protected request and Livewire update, and recheck queued sensitive work. Disabling a user revokes sessions and other credentials/tokens as appropriate.
- No universal administrator bypass for private conversations or personal prompts. Prevent self-escalation, unauthorized delegation, and removal/disable of the last active super administrator.
- Apply visibility before pagination, aggregates, search snippets, calendar feeds, downloads and notifications. Explicit read access never grants editing/upload rights. Attendance and event management are distinct from audience grants.
- Use one prompt entity with personal/library source; one private file metadata system; immutable guideline/report/communication versions. Derive usage/favorite counts from facts until measured performance calls for caches.

## Security and integration rules

- Use Laravel validation, password hashing, CSRF, sessions, rate limits, policies and safe query binding. Treat every Livewire public property/action argument as untrusted. Validate IDs, parent relationships and all mutating state on the server.
- Keep credentials in protected server-side configuration. Never expose OpenAI keys through Blade, Livewire, JavaScript, `VITE_*`, client-readable settings, logs or commits. Commit non-secret `.env.example` only.
- Google linking requires verified provider identity and safe authenticated linking; never merge an existing user solely by matching email. OTPs are expiring, one-use, attempt-limited keyed verifiers with resend throttles. All login methods share account/MFA finalization checks.
- Use private Laravel Storage, randomized keys, extension/MIME/signature/size validation, quarantine, fail-closed malware scanning, and authorized previews/downloads. Binaries never go into MySQL. Reject executable/HTML/SVG uploads initially; bound archive/extraction work.
- AI calls run on the backend through the adapter, with model access, context authorization, budget reservation, ordered messages, idempotency, timeouts, safe retry handling and usage records. Do not blindly retry an ambiguously accepted paid request.
- Treat AI/document text as untrusted data; sanitize rendered output and never execute prompt templates or arbitrary tools. Preserve exact source versions used by AI. Provider retention is distinct from local history and requires an explicit data-handling policy.
- Write redacted administrative audits/event activity for relevant changes; never log OTPs, credentials, full private content or raw tokens. Dispatch side effects after commit and make jobs retry-safe.
- External email/social posting is not implied by event communication draft generation. Do not send messages or publish content without explicit scope/authorization.

## Localization and UI rules

- English and Arabic from the first UI increment. Put interface/validation text in `lang/en` and `lang/ar`; database content is separate from interface translations.
- Set HTML language/direction, use logical spacing/alignment and locale-aware formatting, and isolate LTR identifiers in Arabic layouts. Store UTC instants and IANA timezones.
- Design separate readable mobile interactions: compact navigation, stacked forms, list alternatives and touch-friendly controls. Verify keyboard access, focus, contrast and long translations.
- Do not fabricate dashboard metrics or expose links/buttons for unimplemented modules. Add dashboard summaries with their real module data.

## Required implementation-phase workflow

1. Review existing project, Git changes, instructions, migrations, dependency versions and previous phase report.
2. Write a short plan and scope-specific acceptance criteria.
3. Implement only the approved phase: required migrations, backend, UI, authorization, validation, security and translations.
4. Run required safe local migrations; run meaningful automated tests and the frontend production build.
5. Start locally, verify in the browser and inspect errors. Test desktop 1440×900, laptop 1280×800, tablet 768×1024 and mobile 390×844, including Arabic RTL and English LTR; spot-check 360px width.
6. Fix discovered issues and repeat checks. Distinguish real provider smoke tests from fakes. Never claim an unavailable test passed or a blocked phase is complete.
7. Write `docs/phases/phase-NN.md` with evidence, commands, outcomes and limitations.
8. Create a local Git checkpoint only for intended changes. No automatic push/deploy. Stop and report the phase before the next requested scope.

Planning-only work has no app to migrate/run/browser-test; report these checks as not applicable. Use the roadmap's full acceptance criteria for implementation.

## Local environment and Git care

- Workspace uses PowerShell on Windows. PHP/Composer are on PATH; inspected Laragon Node and MySQL binaries are not. Consult ENVIRONMENT.md, and reinspect before use; its inventory does not prove a server is running.
- Phase 1 uses an isolated MySQL instance at `127.0.0.1:3307` under ignored `.runtime/mysql`, with separate `maktoobe` and `maktoobe_test` databases/users. Phase 2 adds a separately credentialed `maktoobe_browser` database for browser mutations. See README.md and the guarded setup scripts. Never reinitialize an existing runtime. PHP tests reject cached configuration and any database/user other than `maktoobe_test` before RefreshDatabase can run; browser fixtures enforce the browser database/user before reset.
- Queue integration tests use DatabaseMigrations on the disposable PHP-test database to verify real commits, rollbacks and worker execution. Browser tests own a server on port 8001 and may reset only `maktoobe_browser`; manual preview remains on 8000.
- Keep explicit runtime paths or process-scoped PATH changes local to the project session. Never initialize over an existing MySQL data directory or assume credentials.
- Preserve `my task.txt`, existing Git history and unrelated user changes. Use a `codex/` branch for new work unless instructed otherwise. Do not amend unrelated commits, rewrite history or push checkpoints automatically.
- Do not spawn subagents unless the user explicitly asks for delegation. Keep work within the current phase and current task.
