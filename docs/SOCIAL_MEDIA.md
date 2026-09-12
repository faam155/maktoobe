# LINKEDIN PHASE — implementation contract

Authorized by the user's 2026-09-12 attachment. The previous Phase 18 stop boundary is superseded for this module only.

## Plan and acceptance

1. Add platform-neutral social tables, explicit metric periods, policies and additive permissions. Verify constraints and authorization with disposable MySQL tests.
2. Implement authorized account/post/campaign/tag/target/metric administration, historical revisions, preview-confirm imports, analytics queries and event integration. Verify overlap, duplicate, comparison, missing-data and visibility cases.
3. Deliver English/Arabic dashboard, charts, detail screens, management summaries, AI adapter integration, report exports and synchronization ledger. Verify identical filter semantics across outputs.
4. Run the full PHP suite and frontend build, responsive browser checks at 1440, 1280, 768, 390 and 360 pixels in both locales, then record results and create a local checkpoint. No push or deployment.

## Metric semantics

- Counts represent interval activity, never sums of cumulative lifetime snapshots. Each account/post has non-overlapping inclusive reporting-date intervals. A daily row spans one day; a monthly row spans a full calendar month. Mixing overlapping monthly/daily entries is rejected. Corrections append a revision before replacing the current fact.
- Reporting dates use the social account's IANA reporting timezone; post publication instants use UTC. An interval is included only when fully inside the selected period. Partial monthly coverage is explicitly reported and never prorated or represented as zero.
- Account metrics and post metrics are independent measurements. The unfiltered executive overview uses account facts; content/campaign/event/language filters use visible post facts. These sources are never added together. Followers and audience cannot be attributed to a content filter and display unavailable there.
- Followers are end-of-interval snapshots. Starting followers require a prior boundary snapshot; new followers are gross gains recorded by the source, distinct from net follower change. Missing baselines, missing measurements and zero denominators produce unavailable percentages. Weighted engagement rate is sum(engagement components)/sum(impressions), not a sum/average of rates. Formula uses validated component choices, never executable expressions.
- Campaigns and initiatives use the same normalized grouping model with a kind discriminator; a post belongs to at most one grouping. Tags are many-to-many and their totals overlap intentionally. Existing Event FKs and EventAccess apply before post lists, aggregates, exports and AI. No event table duplication.
- Comparisons use explicit calendar periods or equal-length preceding periods. Rates show percentage-point differences as well as relative percentage change. Targets are evaluated against their exact monthly/yearly period; partial/filter-incompatible targets are unavailable. Performance score uses versioned configuration, normalized target ratios and configurable weights/thresholds; insufficient inputs do not become an invented score.

## Security and integration

Corporate social accounts are shared by active verified users with explicit LinkedIn capabilities. Linked event content additionally requires current EventAccess. No role-name bypass. Existing audit writer is extended with sanitized social identities. Uploads remain private, bounded and scanned through the existing scanner before parsing. Imports use immutable private previews, actor ownership, hash validation and transactional confirmation. No remote thumbnails are fetched by the server.

The initial LinkedIn adapter fails closed with a clear unavailable status until a real provider is configured. Refresh records a durable synchronization outcome; it does not pretend to fetch data. No social posting or external messaging. Future adapters consume the same normalized interval contract. Public API controllers and Flutter remain future work.

Reports and AI retain filter/configuration provenance and are reauthorized on every read. AI uses the existing backend provider/model controls; no browser credentials or arbitrary model/tool execution. Development fixtures are opt-in and forbidden outside local/testing/browser environments.

## Implemented boundaries

`SocialMediaAccount` / `social_media_accounts` are corporate analytics identities. The pre-existing `SocialAccount` / `social_accounts` remain Google sign-in identities and are unchanged. Platforms and bilingual content types are code-seeded catalogs. Posts, campaigns, tags and accounts use soft deletion where supported; metric corrections append `social_metric_revisions`. Deletion is not physical erasure.

The migration adds 16 tables: `social_platforms`, `social_media_accounts`, `social_content_types`, `social_campaigns`, `social_tags`, `social_posts`, `social_post_tags`, `social_account_metrics`, `social_post_metrics`, `social_metric_revisions`, `social_audience_metrics`, `social_targets`, `social_sync_logs`, `social_imports`, `social_reports`, and `social_ai_insights`. It uses MySQL foreign keys, uniqueness and reporting-period/target/audience checks. Existing events are referenced by FK; inaccessible events are removed before social post queries and aggregates. Account-level measurements remain corporate totals, independent of event/post visibility.

Shared Actions own mutations, `SocialAccess` and `SocialPostPolicy` enforce capabilities and event visibility, and `SocialAnalyticsQuery` supplies UI, reports and AI. `PublishingQuery` provides reporting-timezone publication buckets. Controllers stay feature-scoped in the existing monolith. The sixteen `linkedin.*` capabilities are additive; the catalog seeder adds them to the existing protected administrator role without resetting role assignments. Import, manual entry and API ingestion require their respective capabilities. The request cannot choose its trusted ingestion source.

Reports retain actor-owned filters, configuration, visibility fingerprint and editable summary. Exports recalculate currently authorized data; they are not frozen binary archives. A changed permission/source fingerprint suppresses stored private summaries and AI results. PDF uses local Playwright Chromium with JavaScript disabled and network requests blocked; XLSX uses OpenSpout with explicit text cells. Temporary exports are private, no-store and removed after sending. Export, write, read and AI throttles have separate named buckets.

AI uses the existing provider/model access contract plus `use-ai`, social AI capabilities, an encrypted snapshot/result ledger, per-user pending-operation locking and a configurable daily output-token reservation. Workers recheck authorization, model and snapshot provenance before and after generation. Single-attempt jobs do not blindly retry ambiguous paid requests; reservations for failed ambiguous work remain charged against the local output budget. These records are separate from private chat history. Actual metrics are calculated independently of generated recommendations.

## Operations and limits

- Safe local additive setup: `php scripts/social-setup.php`. It verifies local environment, uncached configuration and the exact `maktoobe` / `maktoobe_app` identity at port 3307 before migration and catalog seeding. It never refreshes the database. Other deployment environments use their reviewed normal migration process.
- Entry point: `/app/social-media/linkedin`. Create a real account before entering real metrics. The normal seed process does not create demo accounts or statistics. `SocialMediaDemoSeeder` is explicit local/testing/browser-only data; automated browser fixtures use the separate browser database.
- Social worker: `php artisan queue:work --queue=social --tries=1 --timeout=120`. AI jobs use the existing `ai` queue and provider configuration. There is no automatic LinkedIn sync schedule or publishing action.
- PDF host prerequisite: install production npm dependencies and Playwright Chromium (`node node_modules/playwright/cli.js install chromium`); provide a reachable Node executable through `SOCIAL_NODE_BINARY`. `SOCIAL_CHROMIUM_BINARY`, `PLAYWRIGHT_BROWSERS_PATH` or `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` may select an installed browser. Never commit host paths or credentials. Missing runtime returns a safe unavailable response.
- Imports accept the exact downloadable CSV template or equivalent first XLSX worksheet, at most 5 MB / 1,000 rows. XLSX expansion is capped at 20 MB / 200 entries; formulas, macros and external links are rejected. Post metric rows refer to an already-created account-owned post. Imports do not create post metadata or automatically map arbitrary LinkedIn export formats. Previews expire after 24 hours and confirmation is atomic and repeat-safe. The production scanner must be configured; there is no production bypass.
- Reports are bounded to 367 inclusive days; charts are grouped before rendering. Option lists, grouping results, audience rows and recent AI results are bounded. Top posts support 5/10/20. Tags overlap intentionally. These limits prevent unbounded request payloads; very large installations may need authorized search endpoints and measured query tuning in a future scope.
- Monthly/annual targets are editable records. Annual additive counts may be distributed into twelve months only when no monthly targets already exist; December receives the integer remainder. Rates are not divided across months. Score inputs require exact matching targets and sufficient measurements.
- Future LinkedIn credentials, OAuth/provider mapping, automatic sync, other social adapters, Content Calendar planning, public/mobile API controllers, Flutter and automatic insertion into event report files remain integration points, not implemented services. No messages are sent or posts published.
