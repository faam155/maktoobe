# Prompt Library conventions

Phase 6 implements the centralized Prompt Library on the shared `prompts` entity. Administration creates only `source=library`; `source=personal` is reserved for later personal-prompt workflows. Prompt status is draft, published or archived. Editing a published prompt increments its revision and returns it to draft so changed content cannot remain published without review.

`PromptAccess` is the reusable server-side visibility scope. User lists, details, available category/tag filters and copy tracking all require a published library prompt, a present publication time, an active non-deleted category when categorized, and one matching audience rule: all users, owner-only private, explicit user, or current role. Administrators with `manage-prompts` may preview library drafts but receive no bypass for future personal prompts. Publishing additionally requires `publish-prompts`.

Tags are normalized by case-folded canonical name and related through `prompt_tag`. Selected-user and selected-role audiences live in separate pivots with the grant actor and timestamp. Changing visibility clears irrelevant audience pivots. Duplication copies content/category/tags into a new private draft and never copies publication or audience grants.

Search uses bound title/description matching, a MySQL FULLTEXT content index in boolean mode, and authorized tag relations. Visibility is applied before search, category/tag discovery, sorting, counts and pagination. Usage counts derive from immutable `prompt_uses` facts. Phase 6 records idempotent copy operations keyed by user and client UUID; the reserved `ai` kind is not written until AI execution exists.

Prompt content is rendered as escaped text in a `pre/code` surface. Copy uses the browser Clipboard API with a local selection fallback and then posts an authorized, CSRF-protected, rate-limited usage fact. No prompt content is executed or interpreted as HTML, and no OpenAI request occurs in this phase.
