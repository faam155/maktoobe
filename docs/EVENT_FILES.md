# Event photos and files

Phase 13 enables Photos and Documents in both event workspaces. Report and communication categories are file collections only: report versioning, report editing and communication authoring are not implemented. Their specialized tabs remain disabled.

## Data and authorization

`event_files` is the single event-file metadata table. It holds the event FK, fixed category enum (photos/reports/communications/designs/other), original display name, randomized private storage key, detected MIME/extension/size, caption, display order, scan state, uploader FK, timestamps and soft deletion. No binary content is stored in MySQL. The indexed event/category/deletion/order/id lookup supports 24-item pages. Event hard deletion is RESTRICTed; uploader hard deletion sets its FK to null. No production files or credentials are seeded.

Reads inherit the current `EventAccess` audience. Upload requires that audience access plus `upload-event-files`; event ownership alone grants no upload ability. Update/delete additionally require uploader ownership or `manage-events`. Managers still need `upload-event-files`. Explicit event/file parent matching prevents using an accessible event ID with an unrelated file ID. Policies protect shared Actions and controller operations; active/verified middleware protects both portal/admin requests and recent authentication plus throttling protects mutations.

`UploadEventFiles` validates the entire batch before creating metadata. The event row is locked and its access rechecked before inserts and redacted activity records. Caption/category/order updates and deletes use authorized transactional Actions. Physical contents are immutable: changing metadata never replaces bytes. A future report-version row can refer to a new `event_file_id`.

## File handling

- Current limits match inspected local PHP settings: 5 files, 2 MiB per file, 6 MiB aggregate (PHP limits are 2M/8M). Raising these requires coordinated application, PHP/web-server and test changes.
- Accepted: PNG, JPEG, WebP, PDF, DOCX and UTF-8 TXT. Photos require an image. SVG, HTML, scripts, generic archives, macros and arbitrary executable formats are not accepted. PDF/DOCX/TXT download as attachments; inline preview is image-only.
- Extension, server-detected MIME and signature/package checks are applied. Image dimensions are bounded to 8000 per axis and 24 million pixels. DOCX packages are bounded to 500 entries and 20 MiB expanded size; encrypted, traversal and embedded binary/executable entries are rejected. No archive extraction occurs.
- PHP's private, non-addressable upload temp files are the initial quarantine. The whole batch must pass inspection and `PrivateFileScanner` before files become available in private application storage. Database failure cleans up newly written paths; unreferenced bytes from a process crash require a later safe retention cleanup process.
- `PrivateFileScanner` currently delegates to the existing `GuidelineFileScanner` binding, retaining compatibility with Phase 10. The local/testing/browser implementation is only a test adapter: it rejects the antivirus test marker but is not a real malware scanner. Outside those environments it fails closed. Bind an actual scanner to `PrivateFileScanner` (or the shared guideline adapter) before enabling production uploads; do not remove the environment guard.
- Keys are generated as `event-files/{event-id}/{uuid}.{validated-extension}` on the non-public local disk. Client paths/disks/ownership IDs are ignored. Disk/path fields are hidden from model serialization and never rendered in HTML.
- Downloads/previews check authorization, parent, soft deletion, clean scan state, fixed disk, generated path shape and physical existence. They use `nosniff`, `private, no-store`, a restrictive sandbox CSP, and no public/signed storage URL. Browser images use lazy loading; thumbnails/derivative processing are deferred.

Delete is a confirmed soft delete: the gallery and all serving routes immediately stop exposing the file. Bytes remain private; this phase does not invent a retention duration or erase retained data. A separately approved purge workflow is required. Soft-deleting an event also makes its files inaccessible.

## UI and tests

Blade provides progressively enhanced multipart uploads. JavaScript shows actual request upload progress, then a separate validating/scanning state; it renders server validation messages as text, prevents repeated submission while running and offers a generic recovery error for network/session failures. Without JavaScript the normal form still works. Expired recent authentication navigates to password confirmation; file selection must be repeated afterwards.

The responsive gallery, category filter, captions, display ordering, authenticated preview/download and explicit checkbox deletion work in Arabic RTL and English LTR. No new package is installed. Feature tests use disposable MySQL and fake private storage; browser tests own the guarded browser database and use generated PNG fixtures, not real user photos.
