# Event reports

Phase 14 enables Reports in both event workspaces. Each event has one logical `PRE_EVENT` and one `POST_EVENT` report, created on first upload. Each upload adds an immutable numbered version with its own title, notes and private `event_files` row. The highest non-deleted version is current; previous versions remain downloadable. There is no speculative approval/status workflow.

## Data and mutations

`event_reports` holds event/type identity, creator, timestamps and soft deletion. `event_report_versions` holds event/report/file references, monotonically increasing version number, title, notes, creation time and soft deletion. File metadata and uploader identity belong to the referenced EventFile; they are not duplicated. Composite foreign keys enforce matching event parents, unique constraints enforce report type and version identity, and the history index supports descending ten-version pages. Each report type has a separate page parameter.

`UploadEventReport` delegates validated file handling to `UploadEventFiles`, whose internal callback runs inside the same event-locked transaction. Authorization is rechecked under that lock before allocating the next version. Database failures clean up new private bytes. `DeleteEventReport` soft-deletes the logical report, all visible versions and their files together, recording redacted activity. Uploading after deletion starts another visible history without restoring removed versions or reusing their numbers. Physical purge is a separate retention decision.

## Authorization and files

Reads require current EventAccess. Creation requires that access and `upload-event-files`; replacement/deletion also require report creator ownership or `manage-events`. Administrator routes additionally require their existing management middleware. Parent checks protect nested download routes; active/verified account checks, recent authentication, throttling and CSRF remain in force.

Structured report files cannot be edited/deleted through generic file actions and are excluded from the generic Documents list. Legacy files in the Reports category remain ordinary files; no silent migration invents report history. Downloads share EventFileResponse, checking current visibility, deletion, scan state, private disk/path shape and physical existence. No storage path is rendered.

PDF, DOCX and XLSX are accepted, up to 2 MiB per file. MIME, extension and signature/package checks precede the shared scanner. Office archives are bounded to 500 entries/20 MiB expanded size and reject traversal, encryption and embedded binary entries. XLSX requires its workbook and content-type entries. All documents download as attachments with restrictive response headers. Production scanning remains fail-closed until a real adapter is connected; local/test scanning is not malware protection.

## UI and future processing

English/Arabic report sections show current/previous labels, uploader, upload time, size, notes and private downloads. Multipart forms work without JavaScript; enhanced uploads show transfer/scanning progress and reload even when the destination differs only by its report fragment. Deletion requires explicit confirmation. Layouts stack on narrow viewports.

Future extraction or AI work can reference an exact immutable version ID and reuse authorized private file access through feature Actions/adapters. No summarization, comparison, extraction, RAG, generation, external transfer or processing job is implemented in this phase. No package was added.

Tests: `tests/Feature/EventReportTest.php` and `tests/Browser/event-reports.spec.js`; see the phase report for regression evidence.
