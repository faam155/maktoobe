# Phase 14 — Event Reports

Status: completed and verified on 2026-09-02. Local checkpoint: `Phase 14: Implement event reports`.

## Review and scope

Reviewed AGENTS.md, the Phase 13 checkpoint (`fb58ced`), existing EventAccess and file policies, schema, private storage/scanning, upload Actions, workspace, translations and test infrastructure. The user explicitly authorized structured event reporting, superseding the original proposed phase ordering. No later module is authorized.

Plan: reuse private file handling; add normalized logical reports and immutable versions; protect report history against generic file mutations; activate bilingual Reports in both workspaces; verify constraints, permissions, files, responsive layouts and regressions. Existing Laravel/PHP/MySQL dependencies and lockfiles remain unchanged; no package was installed.

## Implemented

- Separate pre/post-event report sections with title, notes, version, current/previous indicators, uploader, date, size and private downloads.
- New versions preserve prior file bytes and history. Event-locked transactions allocate version numbers; metadata failures roll back and clean up new storage writes.
- Read access inherits event visibility. Upload requires the existing file capability; replacement/deletion additionally require report creator ownership or event management permission. Parent checks, CSRF, recent authentication and throttling remain enforced.
- PDF/DOCX/XLSX inspection and shared scanning; explicit confirmed logical deletion retains bytes but revokes access. Generic Documents mutations cannot bypass version protection.
- Ten-version pagination per report type, responsive English/Arabic portal and administration views, progressive upload feedback and empty/error states.
- Immutable version references and existing Actions/private adapters prepare future AI integration without adding extraction, summarization, comparison, generation or RAG.

Important files: `app/Actions/Events/{UploadEventReport,DeleteEventReport,UploadEventFiles}.php`, `app/Models/{EventReport,EventReportVersion}.php`, `app/Policies/EventReportPolicy.php`, `app/Queries/Events/EventReportIndexQuery.php`, `app/Http/Controllers/EventReportController.php`, `app/Services/Events/{EventFileInspector,EventFileResponse}.php`, report migration, routes, `resources/views/events/reports.blade.php`, English/Arabic translations, shared upload JS/CSS and report tests. Existing EventFile policy/query/model and workspace tabs were integrated. See [conventions](../EVENT_REPORTS.md).

## Database

Verified local development identity `maktoobe` / `maktoobe_app@127.0.0.1`. Applied `2026_09_02_001200_create_event_reports` as batch 16; migration status confirms applied. No user data was reset. Adds `event_reports`, `event_report_versions` and the scoped EventFile unique key. Composite foreign keys prevent cross-event references; unique report/type and version constraints preserve identity. Uploader/file metadata remain normalized in EventFile. PHP/browser verification uses separately guarded disposable databases.

## Verification

- Focused PHP report suite: 11 passed, 95 assertions. Covers upload, versioning, file validation, ownership/capabilities, audience revocation, parent tampering, generic-file bypass, deletion, rollback/cleanup, foreign keys and pagination.
- Initial full PHP regression: 166 passed, 1067 assertions (98.22 seconds), including migration rollback/reapply. Final repeat: **166 passed, 1067 assertions in 135.98 seconds**.
- Focused real Chromium browser suite: 6 passed (4.1 minutes). Admin and portal report flows checked at 1440×900, 1280×800, 768×1024, 390×844 and 360×800 with English LTR and Arabic RTL; no horizontal page overflow or console errors. Historical download filenames, invalid uploads and private download denial were tested. Reviewed desktop English and mobile Arabic portal screenshots in ignored `test-results/event-reports-*/reports-*.png`.
- Fixed a Blade directive compilation error discovered by PHP tests. Browser checks exposed a successful upload redirect that changed only the URL fragment and did not refresh report data; the shared upload handler now reloads the same resource after success. Browser tests also use the explicit submit selector rather than matching the native file-picker button.
- Pint, Composer validation/platform checks and production Vite build passed. Local preview runs on port 8000; browser inspection confirms guests are redirected to login without console errors. Automated browser flows run on isolated port 8001.
- Application log inspection shows no new browser request error; retained test log entries include the earlier, fixed Blade compilation failure. The local daily log contains the previously attempted unavailable Tinker command, not a report request failure.

- Final full Chromium regression: **87 passed in 24.1 minutes**, including all six report tests and all previous browser tests. No failures or new application-request errors. Final Pint and whitespace checks passed.

## Remaining limits

Production requires a real malware scanner; local/test scanning is explicitly not production protection. Upload limit is 2 MiB per document. Deletion retains private bytes and requires a separate purge policy. No report-specific approval workflow, AI processing or automatic conversion of legacy generic files was invented. Browser viewport emulation is not physical-device testing. No push or deployment.
