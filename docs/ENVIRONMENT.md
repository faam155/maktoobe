# Environment inspection

Initial inspection on 2026-08-31, before Phase 1. The inventory below is historical readiness evidence, not the current installation state.

**Phase 1 update, 2026-08-31:** Laravel and frontend dependencies are now installed and locked. A project-local MySQL instance and application have been started and verified. See [Phase 1 versions and verification](phases/phase-01.md) and the [setup README](../README.md) for current details. Existing Laragon data was not used or changed.

## Repository

- Workspace: `C:\Users\WINDOW 11\Documents\ChatGPT\maktoobe`.
- Existing Git repository, branch `main`, tracking `origin/main`; initial checkpoint `089e445` (`Initial commit`). Initial working tree was clean.
- The only tracked project file was an empty `my task.txt`. Preserve it.
- No Laravel application, `artisan`, `composer.json`, `composer.lock`, `package.json`, migrations, tests, or application environment file exists.
- No existing `AGENTS.md` was found in the workspace or inspected ancestor directories. No `.openai/hosting.json` exists.
- Git author name and email are configured; their values were not copied into this report.

## Confirmed local tools

| Tool | Observed version | Executable / status |
| --- | --- | --- |
| PHP CLI | 8.4.8, ZTS, Windows x64 | `C:\php\php.exe`, on PATH |
| Composer | 2.8.9 | `C:\ProgramData\ComposerSetup\bin\composer.bat`, uses the PHP above |
| Node.js | 22.12.0 | `C:\laragon\bin\nodejs\node-v22\node.exe`, not on PATH |
| npm | 10.9.0 | Verified by running the adjacent `node_modules/npm/bin/npm-cli.js` with that Node executable |
| MySQL client | 8.4.3 Community | `C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe`, not on PATH |
| MySQL server binary | 8.4.3 Community | Adjacent `mysqld.exe`; binary version verified, server connection not tested |
| Git | 2.53.0.windows.3 | Available on PATH |
| Laravel / Livewire / Tailwind | Not installed in this project | Proposed target versions are in ARCHITECTURE.md |

Laragon also contains a PHP executable in a directory named `php-8.3.16-Win32-vs16-x64`; that alternate executable was not version-tested. Use one explicit PHP runtime consistently for Composer, web serving, workers, and tests.

PHP extensions observed include `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `curl`, `fileinfo`, `intl`, `bcmath`, `dom`, `xml`, `zip`, and `sodium`, alongside core Laravel requirements. Neither GD nor Imagick appeared in `php -m`; add an appropriate image processor before the photo-processing phase. Native Windows also lacks the Unix process-control environment expected for production queue supervision; verify timeout behavior locally and use Linux workers in production.

## Readiness limits

- No running `mysqld`/`mariadbd` process or matching Windows database service was returned by the checks. Laragon may manage MySQL without a Windows service. Database startup, credentials, port, and connectivity remain unverified.
- No application database or test database has been created. Never initialize over an existing Laragon data directory or assume blank/root credentials.
- Docker was not found on PATH or at the checked conventional location. Docker is not required for this plan.
- Node 22.12.0 meets the current Vite documented minimum for Node 22, but the exact chosen Laravel/Vite dependency set still needs resolution. [Vite requirements](https://vite.dev/guide/).
- Installed runtime patch versions are an inventory, not an endorsement for production. Check and apply supported security patches during environment setup and before deployment.
- Package download access, lockfile compatibility, SMTP delivery, Google OAuth, SMS delivery, OpenAI access, malware scanning, storage credentials, and production hosting have not been tested.

## Phase 1 setup approach, after design approval

1. Reinspect the repository and confirm the approved scope; preserve planning files and the existing Git history when adding a Laravel skeleton.
2. Resolve compatible stable dependencies against the chosen PHP version and write lockfiles. Do not force incompatible dependencies or suppress platform checks.
3. Use explicit local runtime paths or a process-scoped PATH addition. Do not change the machine-wide PATH as an incidental project edit.
4. Verify the existing Laragon MySQL configuration without printing secrets; start the existing instance safely and provision separate application and test databases with least-privilege credentials.
5. Keep `.env` and secrets untracked; provide only non-secret examples. Validate the target database before migrations and before tests.
6. Establish MySQL-backed tests, a frontend build, and local HTTP serving. Record actual commands and the resulting URL in the phase report.

No packages were installed, databases migrated, services started, browser features tested, or application code generated during this inspection.
