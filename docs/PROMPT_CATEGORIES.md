# Prompt category conventions

Phase 5 implements only the category foundation for the future AI Prompt Library. `prompt_categories` owns stable identity and operational fields: slug, icon identifier, display order, active state, creator, timestamps and soft deletion. `prompt_category_translations` owns English and Arabic names/descriptions with a composite `(category_id, locale)` primary key. The locale column is constrained to `en` or `ar`, and translation rows cascade only on a future controlled hard purge.

All administration routes require the existing authenticated, active, verified and `access-admin` middleware chain. `PromptCategoryPolicy` additionally requires `manage-categories` for every read or mutation. Controllers stay thin; mutation Actions validate and authorize again, use transactions, and write redacted account audits. `PromptCategoryIndexQuery` authorizes collection access before applying bilingual search, status filters, ordering and pagination.

New categories append to the order. Moving a category swaps it with the nearest ordered neighbor inside a locking transaction. Deactivation is the normal archive mechanism and preserves references/history. Delete performs a soft delete and, once the `prompts` table exists, refuses deletion when any `prompts.category_id` reference exists. The future prompt creation/update Actions must lock and recheck a category is active and not deleted before assignment.

The idempotent `PromptCategorySeeder` owns the 11 requested initial categories and their translations. Seeded categories have no fabricated creator. The reusable `<x-prompt-category-card>` renders localized category data but is not linked from the portal until the Prompt Library has real routes and visibility policies.
