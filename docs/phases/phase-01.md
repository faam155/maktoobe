# Phase 1 — Laravel foundation

Completed and verified locally on 2026-08-31. Starting checkpoint: `89cc181` (`docs: propose architecture schema and phased development plan`). Branch: `codex/phase-01-foundation`.

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

## Verification evidence

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
