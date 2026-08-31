# Phase 2 — Authentication

Completed and verified: 2026-09-01.

## Delivered scope

- Registration with normalized unique username/email, optional international mobile number, strong hashed password, pending approval and verification notification.
- Password authentication by email or username, remember-me, secure logout, recovery/reset, signed email verification, current-session version checks and other-session revocation.
- Active/pending/disabled lifecycle with a guarded operator command, credential revocation and redacted account audits.
- Socialite Google registration/login and recent-auth linking with OAuth state, verified-email and no-email-auto-merge rules.
- Replaceable SMS gateway with expiring, hashed, session/purpose-bound, single-use OTPs, five-attempt limit, resend cooldown and rate limiting. Encrypted local/test delivery is private and fail closed outside allowed environments.
- English/LTR and Arabic/RTL responsive Blade screens for every requested authentication flow.
- Shared sign-in pipeline and configuration hook for future MFA; no MFA method was enabled.

## Database changes

`2026_08_31_000100_extend_users_for_authentication` adds username, normalized phone and phone verification, status, locale/timezone, credential security version, disable metadata, soft deletion and nullable passwords for provider identities. It adds unique keys, status index and a self-referencing disable-actor foreign key.

`2026_08_31_000200_create_authentication_records` creates `social_accounts`, `otp_challenges` and `account_audits` with unique identity constraints, purpose/expiry indexes and explicit CASCADE/RESTRICT/SET NULL foreign-key behavior. OTP secrets/targets/session identifiers are stored only as keyed digests.

Both migrations ran on the development database as `maktoobe_app`; migration status reports every migration applied. The disposable PHP and browser databases were migrated by their guarded test workflows.

## Verification evidence

- `php artisan test --compact`: 64 passed, 342 assertions against isolated MySQL.
- Playwright authentication suite: 6 passed, covering live password login/error/logout/reset, registration, approval, signed verification, OTP, disabled-session revocation and English/Arabic layouts at 1440, 768, 390 and 360 pixels.
- Full Playwright suite: 18 passed in 1.2 minutes. It also covers laptop 1280, landing-page RTL/LTR, CSRF rejection, invalid input, keyboard flow, console/page/network errors and horizontal overflow.
- `php vendor/bin/pint --test`, `composer validate --strict`, Composer audit and `npm run build` are required at the final checkpoint. The production Vite build completed successfully.
- Manual in-app browser checks used the development server at `127.0.0.1:8000`: invalid login rendered the generic localized error, Arabic registration reported `lang=ar`, `dir=rtl`, no overflow at mobile/tablet/desktop, and browser console errors were empty.

## Limitations

Google credentials and a production SMS/mail provider were unavailable, so external provider delivery was not smoke-tested. Provider logic is tested with doubles and local delivery is tested through encrypted private storage. Production configuration is documented in `docs/IDENTITY.md`.

Account approval is intentionally a trusted CLI operation until a later administration/authorization phase. Role/permission management, MFA/TOTP/passkeys, Google unlink, phone replacement, Sanctum/mobile APIs and business modules were outside this phase.
