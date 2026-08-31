# Maktoobe

An internal AI productivity and event workspace developed incrementally with Laravel. Phase 1 establishes the framework, MySQL, Livewire, Tailwind, tests and English/Arabic UI. Authentication and business modules are intentionally unavailable until later approved phases.

## Requirements

- PHP 8.4 with Laravel's extensions and `pdo_mysql`, `mbstring`, `fileinfo`, `intl`, `dom`, `xml`, `zip`
- Composer 2.8+, Node.js 22.12+ (22.x), npm 10 or 11, PowerShell 7+
- MySQL 8.4. The helper defaults to Laragon's `C:\laragon\bin\mysql\mysql-8.4.3-winx64` binaries, without using Laragon's data directory.

## First-time local setup

```powershell
composer install
$env:PATH = 'C:\laragon\bin\nodejs\node-v22;' + $env:PATH
npm ci
.\scripts\mysql.ps1 -Action Initialize
php artisan key:generate
php artisan key:generate --env=testing
php artisan migrate
php artisan migrate --env=testing
npm run build
```

Initialization creates an isolated data directory under ignored `.runtime/mysql`, binds only to `127.0.0.1:3307`, creates separate `maktoobe` and `maktoobe_test` databases/users, and writes random credentials to ignored `.env` and `.env.testing`. It refuses existing runtime/environment targets and never changes Laragon's data. Pass `-MySqlBase` for another installation or `-Port` for an unused local port. Before browser tests that mutate authentication data, run `scripts/browser-database.ps1` once to create a third isolated database/user and ignored `.env.browser`.

Keep `.runtime` and `.env*` private and untracked. Do not rerun initialization to reset a database. Never use `migrate:fresh` on development data. Tests refuse cached configuration or any database/user other than `maktoobe_test`.

## Start and stop

```powershell
.\scripts\mysql.ps1 -Action Start
.\scripts\mysql.ps1 -Action Status
composer dev
```

Open `http://127.0.0.1:8000`. The PHP server is local-only. Registration, password/username login, recovery, verification and local OTP authentication are available; `/app` is an authenticated placeholder and business/admin modules remain unavailable. Use `php artisan serve --host=127.0.0.1 --port=8000 --no-reload` directly if preferred. Stop PHP with Ctrl+C and the project database with `scripts/mysql.ps1 -Action Stop`.

## Verification

```powershell
composer validate --strict
composer check-platform-reqs
composer lint
php artisan test
npm run build
$env:PLAYWRIGHT_BROWSERS_PATH = Join-Path (Get-Location) '.runtime\playwright'
npx playwright install chromium
npm run test:browser
composer audit
npm audit --audit-level=low
```

The browser suite starts its own local Laravel server on port 8001, separate from your preview on port 8000. Keep port 8001 free. It checks desktop, laptop, tablet, mobile and 360px layouts in English/Arabic. Evidence is saved in ignored `test-results` and `playwright-report` directories. `composer format` applies PHP formatting.

In a restricted Windows sandbox, Vite may need permission to resolve ancestor paths, Playwright may need process permissions to shut down its own server, and package installation/advisory checks need network access. Do not disable TLS or platform checks to work around these restrictions. A test run is complete only when the runner exits, not merely when individual checks show as passed.

## Conventions

Read `AGENTS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/ROADMAP.md` and the current phase report before changes. Interface strings belong in matching `lang/en` and `lang/ar` files. Livewire supplies Alpine; do not load it twice. Add packages and migrations only when the approved phase needs them.

See [foundation conventions](docs/FOUNDATION.md) for the current schema/model boundary, future API reuse, queue worker commands, private storage, logging and test isolation. Database jobs wait for transaction commits. Local files are private and write failures throw. New local setups use daily info logs with 14-day retention; an existing `.env` should use `LOG_STACK=daily`, `LOG_LEVEL=info` and `LOG_DAILY_DAYS=14` to adopt those defaults.

This is a verified local application increment, not a deployment. Google and real SMS/email delivery require server-side production credentials/providers. HTTPS, production workers, secret management, backups, stricter CSP and operational hardening remain deployment responsibilities.
