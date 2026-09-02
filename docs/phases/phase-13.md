# Phase 13 — Event Photos and File Management

Status: completed and verified on 2026-09-02. Local checkpoint: `Phase 13: Implement event photos and files`.

## Review and plan

Reviewed AGENTS.md, Phase 12 report, EventAccess/EventPolicy, the event workspace, schema/migrations, existing private Storage and Brand Guideline inspection/scanning, permissions and test fixtures. Started with a clean working tree at `201a8e7`. The user's explicit Phase 13 scope replaces the earlier proposed communications phase; those specialized features remain deferred.

Plan: add one event-file metadata table and reusable authorized Actions/Query; reuse scanner security; activate Photos/Documents; provide bounded batch uploads, captions/order, private previews/downloads and confirmed deletion; verify MySQL constraints, security, responsive bilingual UI and all previous features.

## Implemented

- Photos/gallery and categorized files (Photos, Reports, Communications, Designs, Other Documents) in portal and admin workspaces, with 24-item pagination.
- Multiple uploads with real transport progress and scanning state, private image previews, downloads, captions, category/order changes, deletion confirmation, empty/error states.
- Server-derived ownership, event-audience read access, upload capability, owner/manager mutation boundaries, parent checks, safe private keys and restrictive response headers.
- Batch extension/MIME/signature/size limits, image dimension and DOCX bounds, scanner rejection, and cleanup on metadata/write failure. Shared scan contract preserves existing Brand Guideline behavior.
- Soft deletion revokes access while retaining private bytes. No report-versioning, communication editor, activity-feed UI, external storage URLs or new packages.

Important files: `app/Actions/Events/{UploadEventFiles,UpdateEventFile,DeleteEventFile}.php`, `app/Queries/Events/EventFileIndexQuery.php`, `app/Services/Events/EventFileInspector.php`, `app/Policies/EventFilePolicy.php`, `app/Http/Controllers/EventFileController.php`, EventFile model/factory/category enum, private scanner contract, `resources/views/events/files.blade.php`, workspace tabs/routes, translations and upload JS/CSS, and `tests/Feature/EventFileTest.php` / `tests/Browser/event-files.spec.js`.

## Database

Verified live connection: `maktoobe`, `maktoobe_app@127.0.0.1`. Applied `2026_09_02_001100_create_event_files_table` as batch 15. The migration adds metadata only with event/uploader FKs, unique storage path and a gallery lookup index. No existing data was reset. Test and browser fixtures remain separately guarded.

## Verification

- Initial focused PHP suite: 10 passed, 92 assertions; expanded checks: 12 passed, 102 assertions, including PDF/DOCX and failed writes.
- Final full PHP regression: **155 passed, 972 assertions in 96.66 seconds**, including migration rollback/reapply in queue infrastructure tests.
- Browser upload/gallery/edit/download/delete passed at all five required viewport sizes in English and Arabic. A validation-message mismatch exposed guideline-specific wording in event errors; the event inspector now remaps it to the correct event-file message. Full browser regression: **81 passed in 15.5 minutes**. Final enhanced focused suite: **6 passed**, covering loaded images and the portal in all five viewport sizes.
- Fixed the maximum-order boundary so subsequent uploads remain editable; the PHP regression covers the bounded value and caption updates.
- Production scanner test calls the Action directly because switching the HTTP test environment to production enables CSRF enforcement before the scanner. Production remains fail-closed.
- Composer manifest/platform checks, Pint, migration status and production Vite build passed. Manual preview at http://127.0.0.1:8000/app/events redirects guests to sign-in and has no console errors. The only new log entry is the attempted unavailable tinker command during connection inspection; no application-request error was observed. Reviewed desktop English, tablet Arabic and mobile Arabic portal screenshots with images loaded. Evidence is in ignored `test-results/event-files-*/files-en.png`, `files-ar.png` and `files-portal-ar.png`. Final Pint and whitespace checks passed.

## Limits

Production requires a real malware-scanner adapter. Current limits are 2 MiB/file, 6 MiB/batch, 5 files. Previews use original bounded images, without generated thumbnails. Delete is logical removal; retained bytes require a separate purge/retention workflow. Browser verification uses viewport emulation, not physical devices. No deployment or Git push is included.

See [event-file conventions](../EVENT_FILES.md) for security and operational details.
