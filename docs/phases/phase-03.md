# Phase 3 — Users, Roles & Permissions

Completed and verified locally on 2026-09-01. Starting checkpoint: `bfc5c29` (`Phase 2: Implement authentication system`). Branch: `codex/phase-03-rbac`.

## Scope and implementation

The existing Phase 1 foundation and Phase 2 identity system were reviewed before changes. The implementation preserves the shared sign-in pipeline, account-status middleware, verification rules, credential versioning, account audits, MySQL isolation, localization, queues, private storage, and existing authentication routes. No prompt, AI, event, brand, analytics, MFA, Flutter, or REST API feature was introduced.

Spatie Laravel Permission 8.3 was installed as the architecture-approved RBAC dependency. The application now has a code-owned catalog of 18 granular permissions and five idempotently seeded roles. Users may hold multiple roles through the package's normalized relations; registration and Google-created accounts receive Standard User after the catalog exists.

The `/admin` area includes a real overview and paginated, localized screens for users, roles, and permissions. Authorized operators can search/filter users, create accounts, inspect verification and account state, edit profiles, disable/reactivate, soft-delete, and assign roles. Operators with the required permissions can create/edit roles, assign allowed permissions, and view paginated members. The permission screen is a read-only catalog because permission keys are application capabilities maintained in code.

Controllers remain thin. Reusable Actions own validated mutations and transactions, `UserDirectory` owns filtered pagination, and Laravel policies protect users, roles, and permissions. All administration mutations require recent authentication and authorize again inside the Action. Credential-affecting changes revoke sessions. Sensitive changes append redacted account audits.

Privilege delegation is limited to the actor's current capabilities. There is no blanket super-user gate bypass. Self-edit, self-disable, self-delete, and self-role changes are denied in administration. The Super Administrator role is reserved, non-super operators cannot manage its holders, and serialized row-lock checks prevent disabling, deleting, or demoting the last active Super Administrator.

No privileged production account or password is seeded. `auth:create-super-admin {email}` safely promotes one existing registered account only while no active Super Administrator exists. The procedure is documented in README and AUTHORIZATION.md.

## Database changes

Migration `2026_08_31_230738_create_permission_tables` ran on the verified `maktoobe` development database in batch 3 and creates:

| Table | Integrity |
| --- | --- |
| `roles` | Unique `(name, guard_name)` and timestamps |
| `permissions` | Unique `(name, guard_name)` and timestamps |
| `role_has_permissions` | Composite primary key; both foreign keys cascade |
| `model_has_roles` | Package polymorphic composite primary key, role FK cascade, subject lookup index |
| `model_has_permissions` | Package polymorphic composite primary key, permission FK cascade, subject lookup index |

The package migration remains vendor-compatible. The application enforces the fixed `user` morph alias instead of pretending the generic subject relation has a user foreign key. The initial seed contains five roles and 18 permissions. The one pre-existing development account had no role and was safely assigned Standard User; no account was promoted and no credential was created or changed. The development database was never reset.

## Verification evidence

| Check | Final result |
| --- | --- |
| `php artisan migrate` / `migrate:status` | Permission migration applied; all six migrations report Ran |
| `php artisan test --compact` | 75 passed, 412 assertions against isolated MySQL |
| Phase 3 Playwright suite | 7 passed, including real forms, authorization denial, RTL/LTR, and all required widths |
| Full `npm run test:browser` | 25 passed in 3.0 minutes; authentication and foundation regressions included |
| Responsive matrix | 1440×900, 1280×800, 768×1024, 390×844, and 360px; English LTR and Arabic RTL |
| Manual in-app browser | Admin login/dashboard, server validation errors, user list, roles, English desktop, Arabic tablet/mobile, no overflow |
| Browser/server errors | No console errors; manual preview stderr empty; no new Laravel error entries |
| `composer lint` | Pint check passed |
| `composer validate --strict` / `composer check-platform-reqs` | Passed |
| `npm run build` | Production Vite assets built successfully |
| `composer audit` / `npm audit --audit-level=low` | No advisories; zero npm vulnerabilities |

The feature suite covers seeded role defaults and idempotence, user creation/edit/search/filter, role assignment and session revocation, permission enforcement on every administration endpoint, active/verified route requirements, disabled accounts, protected users and role, privilege escalation attempts, role creation/edit/membership, safe first-super bootstrap, last-active-super protection, a second-super transition, invalid inputs, and Arabic RTL rendering. The prior authentication and foundation suites remain green.

The application was started on task-owned `http://127.0.0.1:8001` for manual review, then that server was stopped. The existing local preview remains available on `http://127.0.0.1:8000`. Browser tests use the separately credentialed `maktoobe_browser` database and reset only that disposable database.

One browser verification defect was fixed: five rapid viewport logins correctly reached the application login throttle before the workflow test. The workflow now resets its isolated browser fixture and explicitly asserts successful login before testing forms. No production throttle was weakened.

## Important files

- `app/Support/Authorization/Access.php`, `database/seeders/AccessControlSeeder.php`, `config/permission.php`: permission catalog, default role mapping, and package configuration.
- `app/Policies/*`, `app/Services/Authorization/*`, `app/Actions/Administration/*`: server-side abilities, delegation limits, last-super locking, and mutation boundaries.
- `app/Http/Controllers/Admin/*`, `app/Queries/Administration/UserDirectory.php`, `routes/admin.php`: protected administration transport and pagination.
- `resources/views/admin/*`, `resources/views/components/layouts/admin.blade.php`, `resources/css/app.css`, `lang/en/admin.php`, `lang/ar/admin.php`: responsive bilingual administration UI.
- `app/Console/Commands/CreateFirstSuperAdministratorCommand.php`, `docs/AUTHORIZATION.md`: safe bootstrap and operational conventions.
- `tests/Feature/AdministrationAuthorizationTest.php`, `tests/Browser/administration.spec.js`, `scripts/browser-fixtures.php`: authorization, governance, workflow, and responsive coverage.

## Limitations

Permission definitions are intentionally code-owned; the UI assigns permissions to roles but does not create, rename, delete, or directly grant permission records. Role deletion was not requested and is not exposed. User deletion is a reversible soft delete with access revocation, not a privacy-erasure workflow. Account audits are written but a general audit-log browser was outside the requested Phase 3 screens.

No real Google, SMS, or mail provider was available; Phase 2's provider limitation is unchanged. Verification used Chromium viewport emulation rather than physical devices or Safari/Firefox. The development database intentionally has no auto-created Super Administrator; an operator must select an existing registered account with the documented one-time command. Production deployment, HTTPS, supervision, backup/restore, and external log retention remain operational work.

Stop after Phase 3. Do not start another phase until the user requests it.
