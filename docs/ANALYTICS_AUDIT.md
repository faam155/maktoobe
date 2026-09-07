# Analytics and audit conventions

Phase 17 adds `/admin/analytics` and `/admin/audit-logs`. Both require active, verified accounts and `access-admin`; their separate capabilities are `view-analytics` and `view-audit-logs`. The latter is added to the code-owned permission catalog and initially granted to existing Administrator and Super Administrator roles without replacing their other grants.

## Reporting semantics

`ReportPeriod` validates Today, Last 7 days, Last 30 days, Current month (month to date), and inclusive custom dates of at most 366 days. Account-local midnight boundaries become half-open UTC intervals. The default is the last 30 days. Custom ranges may include future dates to report upcoming events.

`AnalyticsQuery` uses current authorization before all aggregates. User counts require `manage-users`. Library inventory and rankings use `PromptAccess`; only currently accessible published library prompts qualify. Personal prompts, favorites, AI conversations and requests are **the actor's own** records, not global private-workspace statistics. Event records and child resources use `EventAccess`. The existing administration overview now also limits its AI conversation count to the actor's conversations with `use-ai`.

Creation dates define user/prompt/favorite/conversation/photo/report/communication cohorts. AI Assistant requests use `requested_at`; communication generations use their creation date. Events use their start instant. Status counts reflect current state, not historical snapshots. Soft-deleted records are excluded. Reports count logical reports with a clean current file, not every uploaded version; communications exclude empty slots and archived content. Photos require clean scan status.

AI requests combine the existing Assistant and event-generation ledgers without double counting. Token totals include completed requests with returned totals; successful requests missing totals are counted separately. Prompt and category rankings count recorded copy/AI-use facts in the selected period, not inferred user activity or prompt inventory.

Queries execute only on the reporting page, not on every navigation/layout render. Date indexes, bounded periods, SQL aggregation, top-ten rankings, 30-row audit pagination and route throttles constrain work. No shared report cache is used: cached aggregates could outlive access revocation. Introduce measured rollups/caches only with explicit audience invalidation and defined retention semantics.

## Audit ledger

`RecordAudit` is the central writer. `RecordAccountAudit` writes the legacy row and its sanitized mirror in one transaction. EventActivity model creation mirrors existing event/file/report/communication action facts; domain actions already enclose these in transactions. Explicit event visibility and role permission changes have distinct actions. `source_key` makes legacy import idempotent and preserves original timestamps. Run `php artisan audit:import-legacy` after migration; it copies only existing facts, without inventing missing historical changes.

`SafeAuditMetadata` accepts only known positive resource IDs, boolean change flags, controlled enums, role-ID lists, catalog permission names and approved configuration key names. Names, descriptions, contents, passwords, credentials, paths and arbitrary nested fields are excluded. Eloquent AuditLog creation applies the allowlist again. Existing legacy rows are not rewritten or erased; new writes to both ledgers are sanitized.

The actor FK uses SET NULL; resource identity uses stable code-owned types plus an ID deliberately without a polymorphic FK so deletion does not erase history. Account self-service/CLI actions without a recorded actor remain unattributed rather than inventing attribution. The viewer displays current actor names where available and never snapshots contact information.

The viewer requires both `view-audit-logs` and the relevant module management permission. It supports action/actor/resource/date filters and bound text search across action, resource type and actor name. It exposes minimal administrative facts, not personal prompts or private conversation contents. Model update/delete operations throw and there are no mutation routes. This is append-only application behavior, **not** tamper-proof storage against privileged database access. Production retention, restricted database roles, external archival and integrity controls require a separately approved operations policy.

## Configuration changes

`php artisan audit:configuration` compares SHA-256 fingerprints of explicitly approved non-secret configuration fields under a database lock. Initial observation establishes a baseline. Later changes record only the changed key names with a system actor. AI provider/model/limit/timeout options and app locale/timezone/session lifetime/secure setting are included; API keys and other secrets are never selected or fingerprinted.

Run the command after applying deployment configuration/cache updates. Laravel Scheduler also checks hourly with overlap protection. This detects observed net changes, not every file edit or transient change between checks; it cannot attribute file changes to a person. No AI/settings administration UI was added and no credential-rotation monitoring is claimed.

## Tests and extensions

`ReportingTest` covers scoped calculations, local date bounds, validation, token handling, logical report counts, current visibility, permissions, sanitization, transactional rollback, import idempotency, configuration change detection, filtering and pagination. `reporting.spec.js` exercises both languages, five viewport sizes, filters, metadata, validation and denied access using the guarded browser database.

New sensitive domain actions must call the existing transactional audit adapter with code-owned action/resource identifiers and allowlisted metadata. Do not dispatch audit persistence asynchronously if that would allow a committed mutation without its audit fact. Do not add free-text payloads merely to make the viewer more descriptive.
