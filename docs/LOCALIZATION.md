# Localization conventions

Phase 18 completes the application-wide English and Arabic interface contract.

## Locale resolution and persistence

- `App\Enums\Locale` is the allowlist and the source of document direction.
- The session preference takes precedence for the current browser. An authenticated account preference is used when the session has no explicit choice; the configured locale is the final fallback.
- A guest's explicit session choice is adopted by the account after any successful sign-in through `CompleteSignIn`. An authenticated change is stored in both the session and `users.locale` without creating an administrative audit event.
- Every full-page layout sets `lang` and `dir` from the resolved enum. English is LTR and Arabic is RTL.

## Translation catalog

- User-interface text belongs in matching PHP catalogs under `lang/en` and `lang/ar`.
- Catalog filenames and recursive keys must remain identical. `LocalizationTest` enforces parity, non-empty values and the existence of static translation references.
- Validation attributes use readable localized labels. Add messages for every newly introduced validation rule rather than relying on the framework's English fallback.
- Dates use the active locale; UTC instants and account/event timezones remain the source of truth. Technical identifiers, models, email addresses and filenames use `bdi` or an explicit LTR direction where appropriate.

## Bidirectional content and controls

- User-generated content is preserved as written. Use `dir="auto"` for free-form titles and messages, or an explicit content language when the record provides one.
- Use logical CSS properties. Directional icons use `.directional` or `.back-arrow`, which mirror in RTL. Brand marks and non-directional icons do not mirror.
- A translated label must not be used as a database value, route key, permission name or enum value.
- New screens must be checked in English and Arabic at desktop, tablet and mobile breakpoints, including overflow, focus, dialogs, tables, pagination and empty/error states.

