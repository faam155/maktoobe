# Foundation implementation conventions

Phase 1 extends checkpoint `d473adc`; it does not begin identity or business-module development. The complete target schema remains in [DATABASE.md](DATABASE.md). This document records the conventions already supported by the installed foundation.

## Current schema and Eloquent boundary

| Data | Ownership / constraints | Application representation |
| --- | --- | --- |
| `users` | Unsigned BIGINT ID, unique email, hashed password, nullable verification/token fields, timestamps | `App\Models\User`, `Database\Factories\UserFactory` |
| `password_reset_tokens` | Email primary key, hashed token, creation timestamp | Laravel password broker; no duplicate application model |
| `sessions` | String primary key, nullable indexed user ID, indexed last activity | Laravel database session handler; encrypted payloads |
| `cache`, `cache_locks` | Primary key per cache key, indexed expiration | Laravel cache and lock stores |
| `jobs`, `job_batches`, `failed_jobs` | Framework queue indexes and unique failed-job UUID | Laravel queue, batch and failure providers |

These are eight tables plus the migration ledger. Framework session and reset references deliberately do not have user foreign keys; their lifecycle is managed by Laravel and the future identity actions. There is no current application relationship to implement beyond that framework contract. Do not invent User-to-event/prompt/role relationships before those tables and policies are approved. Do not create redundant models for framework infrastructure tables.

MySQL uses InnoDB, `utf8mb4_unicode_ci`, strict mode and UTC connection time. The schema's email unique index also rejects case variants. Future identity actions must normalize and validate identifiers before insertion. Future domain migrations use explicit foreign keys, composite uniqueness and deliberate deletion rules from DATABASE.md. Do not edit already-applied migrations to introduce a new domain relationship: add a new migration in its phase.

The default seeder creates no accounts. The User factory is test tooling, with verified and unverified states; its known test password must never be used to seed production users. User serialization hides password and remember token, but this is not a public API contract. Later APIs must use explicit API Resources and authorization.

## Folders and future API reuse

Use conventional Laravel folders and create feature subfolders only when their first concrete class is needed:

| Location | Responsibility |
| --- | --- |
| `app/Livewire`, `app/Http/Controllers` | Input and presentation adapters; no duplicate business rules |
| `app/Actions/{Feature}` | Nontrivial authorized mutations and transaction boundaries |
| `app/Queries/{Feature}` | Authorized lists, filters and aggregates before pagination |
| `app/Policies` | Reusable actor/resource abilities, shared across UI/API/jobs |
| `app/Models`, `app/Enums` | Eloquent relationships/casts and validated stable values |
| `app/Jobs/{Feature}` | Retry-safe background work using committed resource IDs |
| `app/Contracts`, `app/Services/{Integration}` | Adapters only when a real external integration needs one |
| `app/Http/Requests`, `app/Http/Resources` | Versioned REST validation/presentation when APIs are introduced |
| `lang/en`, `lang/ar` | Matching translation keys; user content is separate |
| `tests/Unit`, `tests/Feature`, `tests/Browser`, `tests/Fixtures` | Pure rules, integration behavior, browser flows and test-only payloads |

There are no empty module trees, speculative interfaces, repository wrappers, API endpoints, Sanctum tokens or Flutter code. The current locale preference affects only a browser session and may remain in its small Livewire component. Domain actions added later must accept validated values and an explicit actor rather than relying on Livewire state or a global browser session. Future `/api/v1` controllers reuse those actions and policies; mobile requests must not bypass status, verification, audience or file checks.

## Queue and scheduler operation

The database queue is the supported baseline. Dispatch waits for all enclosing database transactions to commit; rolling back drops pending dispatch callbacks. This also avoids workers trying to read uncommitted records. It does not guarantee atomic delivery across a process crash between commit and enqueue; add a durable outbox only when a future feature's reliability requirements justify it. See [Laravel queue transactions](https://laravel.com/docs/13.x/queues#jobs-and-database-transactions).

Run a local worker in a separate terminal when testing approved jobs:

```powershell
php artisan queue:work database --queue=default --sleep=1 --tries=1 --timeout=60
```

`DB_QUEUE_RETRY_AFTER=90` exceeds the 60-second worker timeout. New jobs must define appropriate timeout/attempt/backoff values and remain below their connection's reservation interval. Do not blindly retry paid or otherwise non-idempotent external requests. Windows PHP lacks PCNTL process signals, so this environment does not verify hard timeout enforcement; production Linux workers need PCNTL, supervision and bounded HTTP timeouts.

Future job payloads carry resource IDs and safe correlation identifiers, not credentials or document bodies. Reload current records and permissions at execution, make side effects retry-safe, handle deleted/revoked resources, and dispatch after commit. Failed jobs are persisted in `failed_jobs`; inspect with `php artisan queue:failed`. Retry only after diagnosing the failure. Restart workers after code/config changes with `php artisan queue:restart`.

`routes/console.php` is the scheduler registration point. There are no scheduled business tasks yet; `php artisan schedule:list` should be empty. Add schedules, failed-job/batch retention and monitoring with the module that produces those records. No autonomous pruning or other destructive maintenance is enabled in this phase.

## Storage and logging

The default `local` disk is rooted at `storage/app/private`, is private, has no generated download route, and throws on failed writes. Do not run `storage:link` for internal documents. A public disk remains a separate framework option for explicitly public assets. Future file metadata must store disk/key, and downloads must pass a resource policy before streaming. No upload UI or file metadata table exists yet. MIME/signature/size checks, quarantine and malware scanning are required when uploads are introduced. See [Laravel failed writes](https://laravel.com/docs/13.x/filesystem#failed-writes).

Development logging uses a daily file at `storage/logs/laravel-YYYY-MM-DD.log`, info severity and 14 retained days via `LOG_STACK=daily`, `LOG_LEVEL=info`, `LOG_DAILY_DAYS=14`. Rotation cleanup happens on log writes, not through a scheduler. Production may route logs to stderr or a protected collector with an approved retention policy. See [Laravel logging](https://laravel.com/docs/13.x/logging).

Log safe event names, resource IDs, correlation IDs, status and duration. Never log whole requests, credentials, authorization headers, OTPs, provider tokens, private text or arbitrary exception payloads. This phase does not claim automatic redaction of arbitrary strings; review each future logging call and provider exception boundary. Framework failed-job payloads/exceptions also require private access and disciplined payload design.

## Test and migration discipline

- PHP tests require the separate MySQL database/user `maktoobe_test` with uncached testing configuration. The base TestCase refuses unsafe targets before test lifecycle traits can migrate or reset tables.
- Use `RefreshDatabase` for ordinary integration tests. Queue transaction tests use `DatabaseMigrations` so commits and rollbacks are real; only the disposable database is refreshed. Those tests dispatch a fixture through the real queue worker and verify successful, rolled-back and failed execution.
- Storage tests write a unique temporary file to the actual private disk and remove it in `finally`; no private user file is modified. Write-error tests inject a failing adapter. Logging tests write and clean an isolated temporary log.
- Routine tests fake external providers, not database constraints. Test MySQL uniqueness/FKs and denied access explicitly when introduced. Do not use SQLite as a substitute for relational integration coverage.
- Browser tests use their own server on port 8001 so the Windows single-process preview on port 8000 cannot hold up test navigation. This phase's browser tests use the local development configuration and only change isolated browser-session preferences; they never reset a database or create user records. Introduce a dedicated browser-test database/environment before adding business-data mutation tests. They cover English/Arabic, actual Livewire updates, denied/invalid requests, keyboard navigation and five viewport sizes, not physical devices or every browser.
- Before local migrations, confirm the connection/database. Use `php artisan migrate` and `php artisan migrate --env=testing`; never reset development data. After queue tests, a final testing migration may restore the disposable schema if the last test rolled it back.

Setup and verification commands are in [README.md](../README.md); evidence belongs in the [Phase 1 report](phases/phase-01.md).

Phase 16 now registers notifications:dispatch every minute. It creates duplicate-safe upcoming-event reminders and resumes incomplete notification work. The durable workspace_notices ledger closes the commit/enqueue recovery gap for this feature. Run a dedicated notifications worker; see NOTIFICATIONS.md. No destructive pruning is scheduled.
