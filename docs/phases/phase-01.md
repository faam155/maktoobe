# Phase 1 — Laravel foundation

Completed and verified locally on 2026-08-31. Starting checkpoint: `89cc181` (`docs: propose architecture schema and phased development plan`). Branch: `codex/phase-01-foundation`.

The explicit follow-up request for **Laravel Foundation & Database Architecture** was completed against checkpoint `d473adc`. See [follow-up verification](#foundation-and-database-architecture-follow-up) below for the current 28-test PHP suite and infrastructure changes; the initial implementation evidence is preserved for history.

## Scope and review

Read AGENTS.md, the architecture, database proposal, roadmap and environment inventory before implementation. The repository contained planning documents and an empty `my task.txt`; no prior application, migrations or features existed. The user's instruction to start the next phase authorized the roadmap's Phase 1 foundation only. PHP and Composer were available; Node and MySQL required explicit Laragon binary paths. There was no application database to reuse.

The implementation plan was to install the compatible Laravel/Livewire/Tailwind baseline, configure isolated development/test MySQL databases, build the English/Arabic responsive foundation, then verify migrations, security behavior, tests, build and browser layouts before this checkpoint. No authentication, RBAC, dashboard metrics or business modules were included.

## Installed baseline

| Dependency | Verified version |
| --- | --- |
| PHP / Composer | 8.4.8 / 2.8.9 |
| Laravel framework | 13.29.0 |
| Livewire | 4.4.3; supplies Alpine |
| Tailwind CSS | 4.3.3 |
| Node.js / npm | 22.12.0 / 10.9.0 |
| Vite / Laravel Vite plugin | 7.3.6 / 2.1.0 |
| MySQL | 8.4.3 |
| PHPUnit / Laravel Pint | 12.5.34 / 1.30.5 |
| Playwright | 1.62.1 |

Composer and npm lockfiles are committed. No Socialite, permission package, AI SDK, Sanctum or UI component library was installed. Default Axios, Tinker and Pail were not retained because this phase does not need them.

## Implemented behavior

- A public foundation screen with desktop sidebar, tablet/mobile dialog navigation, keyboard focus handling, reduced-motion support and no links to unimplemented features.
- English LTR and Arabic RTL translations, logical CSS alignment, direction-aware controls and a validated Livewire language preference persisted in the caller's session. A full navigation updates the document language and direction together.
- Shared Blade layout, navigation, brand and icon components; a modest local CSS illustration with no third-party asset requests.
- Localized 404, 419, 429 and 500 views. Unimplemented portal, admin, authentication, API and private-file paths return 404.
- Laravel CSRF protection, enum-based validation, per-session rate limiting, encrypted database sessions, debug disabled, private local storage and baseline security headers. The public preference cannot mutate an account or another session. Authentication policies are intentionally deferred to Phase 2 because no protected feature exists yet.
- Setup examples without secrets, a PowerShell helper for isolated MySQL, guarded MySQL integration tests, PHP formatting and a reproducible browser suite.

## Database changes

Three framework migrations create eight tables, plus Laravel's migration ledger:

| Migration | Tables / constraints |
| --- | --- |
| `0001_01_01_000000_create_users_table` | `users` with unique email; `password_reset_tokens` keyed by email; `sessions` keyed by ID with user and last-activity indexes |
| `0001_01_01_000001_create_cache_table` | `cache` and `cache_locks`, each keyed by cache key |
| `0001_01_01_000002_create_jobs_table` | `jobs` with queue index; `job_batches` keyed by ID; `failed_jobs` with unique UUID |

The framework session `user_id` remains nullable and indexed without a foreign key, matching Laravel's baseline session handler; session revocation/lifecycle is part of Phase 2. Reset-token email is also Laravel's framework key, not a user foreign key. There are no application relationships requiring new foreign keys in this increment. No users were seeded and no prompt, event, role, permission or AI tables were created.

Both databases use MySQL/InnoDB and `utf8mb4_unicode_ci`. Development uses `maktoobe` with `maktoobe_app`; tests use `maktoobe_test` with a distinct `maktoobe_test` user. The server binds to `127.0.0.1:3307`, uses a new project-owned data directory, disables local infile and server-side file import/export, and uses randomly generated credentials. Tests check database/user/environment and uncached configuration before any RefreshDatabase operation. A real database-permission test proves test credentials cannot read development users.

## Initial checkpoint verification evidence

| Check | Result |
| --- | --- |
| `composer validate --strict` | Passed |
| `composer check-platform-reqs` | Passed |
| `composer lint` (`pint --test`) | Passed after formatting |
| `php artisan migrate` | All three migrations applied to the verified development database |
| `php artisan migrate --env=testing` | All three migrations applied to the disposable test database |
| Test-only rollback and reapply | All three migrations rolled back and reapplied successfully; development data was not reset |
| `php artisan test` | Final run: 19 tests, 90 assertions, all passed |
| `npm run build` | Passed; production Vite assets generated |
| `npm run test:browser` | Final rerun: 12 tests passed in 36.0 seconds |
| Route cache / view cache | Compiled successfully and cleared after checks |
| `composer audit` | No security vulnerability advisories reported |
| `npm audit --audit-level=low` | Zero vulnerabilities reported |
| Application / health endpoint | Local app and `/up` responded successfully |
| Browser and application errors | No console/page/network errors during successful browser flows; PHP server stderr empty and no Laravel error log produced |

PHP coverage includes locale resolution/fallback, translation key parity, English/Arabic persistence, invalid and hostile preference values, throttling, CSRF rejection, localized missing routes, framework schema, no seeded users and database credential isolation. Browser tests exercise actual Livewire requests, invalid input, missing CSRF, unavailable routes, keyboard skip navigation, dialog close/focus return and language switching.

Responsive checks covered both languages at desktop 1440×900, laptop 1280×800, tablet 768×1024, mobile 390×844 and narrow mobile 360px. The automated matrix checked overflow, readable primary content, navigation and language forms. Manual in-app browser review inspected desktop English, tablet/mobile Arabic, language switching, the mobile drawer and Arabic 404 recovery. This is Chromium viewport emulation, not physical-device or Safari/Firefox testing.

Selected visual evidence: [desktop English](evidence/phase-01-desktop-en.png) and [mobile Arabic](evidence/phase-01-mobile-ar.png). The full generated matrix and Playwright report remain in ignored local test output folders.

Issues corrected during verification included the MySQL loopback administrator mapping, a mobile language shortcut missing its accessible name, a test selector made ambiguous by that accessibility fix, LTR isolation of the brand mark, and skip-link/screenshot positioning. Tests were rerun after fixes. Restricted network operations and Vite ancestor-path resolution used approved sandbox escalation; TLS and platform constraints were not bypassed.

## Important files

- `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`: framework and build baseline.
- `app/Livewire/Foundation.php`, `app/Enums/Locale.php`, `app/Http/Middleware/*`, `bootstrap/app.php`, `routes/web.php`: locale behavior, public route and request controls.
- `resources/views/components/*`, `resources/views/livewire/foundation.blade.php`, `resources/views/errors/*`, `resources/css/app.css`, `resources/js/app.js`, `lang/en/*`, `lang/ar/*`: responsive bilingual UI and error states.
- `database/migrations/*`, `database/seeders/DatabaseSeeder.php`, `scripts/mysql.ps1`, `.env.example`, `.env.testing.example`: baseline schema and isolated local setup.
- `tests/TestCase.php`, `tests/Feature/FoundationTest.php`, `tests/Unit/LocaleTest.php`, `tests/Browser/foundation.spec.js`, `phpunit.xml`, `playwright.config.js`: regression and safety checks.
- `README.md`, `AGENTS.md` and the architecture/roadmap/environment documents: setup, conventions and current stage.

## Local handoff and limitations

The application runs at **http://127.0.0.1:8000** using built assets. MySQL runs separately on port 3307. Startup and shutdown commands are in README.md; servers must be restarted after a machine/session restart. Runtime data, passwords, application keys, dependencies and generated build/test output are ignored by Git.

This is a verified local foundation, not a production deployment. Registration, login, role permissions, AI, events, file workflows and provider integrations have not been implemented or tested. Production HTTPS, complete CSP enforcement, operational supervision, backups, retention and deployment hardening remain later work. The MySQL binary distribution reports a missing optional ICU regex data directory; current queries do not use regular expressions, but the distribution must be repaired or replaced before regex-dependent functionality is introduced. The local helper requires PowerShell 7 and the documented MySQL binaries; broader OS setup is not claimed.

Phase 2 has not started. Stop at this checkpoint until the next phase is requested.

## Foundation and database architecture follow-up

Completed on 2026-08-31 in response to the explicit Phase 1 scope. Before changes, inspected AGENTS.md, the full file inventory, architecture and schema documents, existing migrations/model/factory/seeder, configuration, previous phase report, dependency versions, Git history and local services. The working tree was clean at `d473adc`. Explained the existing foundation and the remaining configuration gaps before implementation.

### Changes

- Made MySQL the configuration fallback, replaced the default root account with the application account, and specified InnoDB and UTC connection time. The existing local database already uses these settings; no data conversion was required.
- Configured the supported database queue to dispatch after enclosing transactions commit, and made batch/failure configuration default to MySQL. Documented safe payloads, worker attempts/timeouts, rollback behavior, retry limits and scheduler registration. Removed the unused example `inspire` command. No business jobs or scheduled tasks were added.
- Made the local disk explicitly private and enabled exceptions on failed writes. Added tests for actual private-file round trips, HTTP denials, path traversal and injected write failures.
- Configured daily info logs with 14-day retention in defaults and the local environment. Added an isolated log-write test, plus safe logging guidance; no claim of automatic arbitrary-content redaction is made.
- Added real database-queue tests for nested commits, rollback, successful worker execution and persisted failures. Test payloads remain under `tests/Fixtures`; no production probe jobs were introduced.
- Added User factory/model checks for hashing, hidden credentials, verification states and MySQL-enforced case-insensitive email uniqueness. The existing model/factory/seeder remain sufficient; no account or administration feature was introduced.
- Added `docs/FOUNDATION.md` covering the current normalized schema, framework-managed relationships, folder responsibilities, future REST/API Resource/Sanctum boundaries, private storage, queues, logging and test discipline. Updated AGENTS.md, README.md and architecture/database references.
- Gave browser tests a dedicated server on port 8001, separate from the manual preview on port 8000. This phase's browser tests only change browser-session preferences, not business data. A separate browser-test database must precede future business mutation tests.

### Schema and dependency impact

**No new tables, migrations, relationships or packages were necessary.** The existing three framework migrations and eight normalized framework tables plus the ledger remain the initial schema. InnoDB, `utf8mb4_unicode_ci`, UTC, application database identity and zero development users were verified directly. Laravel owns session/reset/queue/cache relationships; no redundant Eloquent models or speculative module foreign keys were added. Future domain constraints remain specified in DATABASE.md and will be migrated with their modules. Composer/npm manifests and lockfiles are unchanged.

### Final checks

| Check | Follow-up result |
| --- | --- |
| `php artisan migrate` | Nothing pending on verified `maktoobe` database |
| `php artisan migrate --env=testing` | Nothing pending; isolated test schema restored/verified after tests |
| `php artisan test --compact` | 28 passed, 129 assertions; final PHP rerun 7.35 seconds |
| `composer lint` | Passed; formatting was applied before checks |
| `composer validate --strict` / `composer check-platform-reqs` | Passed |
| `npm run build` | Passed; production assets generated |
| `npm run test:browser` | Final clean run: 12 passed in 55.5 seconds, exit code 0 |
| Real database worker | Successful, rolled-back and exhausted-job behavior passed using test-only payloads |
| `php artisan schedule:list` / `php artisan queue:failed` | No schedules or development failed jobs |
| Browser/manual review | English desktop; Arabic tablet 768px and mobile 390px; language save, drawer navigation, no overflow and no console warnings/errors |
| Automated viewport matrix | English/Arabic at 1440×900, 1280×800, 768×1024, 390×844 and 360px |
| Application/log inspection | Preview restarted with updated environment; health endpoint passed; no unexpected Laravel log or preview stderr errors |

Two verification issues were resolved: a Windows separator mismatch in a test path assertion now compares resolved paths; a browser navigation timeout on the shared Windows preview led to separate test-server ownership. All checks then passed, but the restricted sandbox could not cleanly stop the test server. After verifying and stopping only task-owned hung/duplicate processes, a full browser rerun with process permissions completed normally without intervention. The README records this sandbox limitation. No application assertions were weakened and no automatic retries or larger test timeouts were added.

The manually reviewed UI is unchanged from the initial screenshots; fresh automated screenshots are in ignored `test-results`. The preview remains at **http://127.0.0.1:8000**; the automatic server on port 8001 stops after the suite. Private probes and temporary logs were cleaned. Existing MySQL data was preserved.

Limitations remain explicit: no authentication/RBAC or excluded business modules, no external providers, no Flutter or REST endpoints, no physical-device/Safari/Firefox coverage, no hard worker-timeout enforcement on Windows without PCNTL, and no production deployment. The previously noted optional MySQL ICU regex-data warning remains outside current queries. Stop after Phase 1.
