# Brand Guideline conventions

Phase 10 stores each logical guideline separately from immutable uploaded versions. Every version uses a randomized key on Laravel's private `local` disk; browsers receive files only through a permission-protected controller with a fixed download name and `nosniff`. Client paths are never accepted.

`manage-brand-guidelines` protects listing, upload, versioning, activation and download at both middleware and policy boundaries. A user with that permission can enter only the guideline administration area unless they independently hold broader administration access. Mutations require recent authentication.

Uploads are limited to 10 MB PDF, DOCX, PNG, JPEG, WEBP and UTF-8 TXT. `GuidelineFileInspector` verifies extension, detected MIME, binary signature and DOCX package structure. `GuidelineFileScanner` is a replaceable adapter. Its local/test implementation rejects the standard antivirus test marker and is prohibited outside local, testing and browser environments, so production fails closed until a real scanner is bound. Only versions recorded as `clean` can be activated or downloaded.

There is at most one active version across the application. Activation locks the guideline set, deactivates the prior version and records a redacted account audit. Existing versions are never overwritten; replacing a document creates a new uniquely labelled version. Physical deletion is intentionally unavailable until retention and purge rules are approved.

AI context is opt-in per request. `BrandGuidelineContext` accepts only the exact active, clean version whose extraction status is `ready`, bounds context to 12,000 characters, and the request snapshots that version ID. The queued job uses that exact version even if activation later changes. Current extraction supports bounded UTF-8 text only. PDF, DOCX and images are stored securely but cannot be used as AI context until reviewed extraction, chunking and retrieval adapters are added. No OCR, embeddings, vector search or RAG capability is claimed.
