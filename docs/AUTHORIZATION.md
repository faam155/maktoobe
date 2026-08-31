# Authorization conventions

Phase 3 uses Spatie Laravel Permission 8.3 with Laravel policies and application Actions. All permissions use the `web` guard. The only supported authorization subject is `App\Models\User`, stored through the enforced morph alias `user`. Future Sanctum endpoints must reuse the same permission catalog, policies, and Actions rather than create an API-specific role system.

## Permission catalog

The code-owned catalog in `App\Support\Authorization\Access` contains:

- Administration: `access-admin`, `manage-users`, `create-users`, `edit-users`, `disable-users`, `delete-users`, `manage-roles`, `manage-permissions`.
- Prompt and brand preparation: `manage-prompts`, `publish-prompts`, `manage-categories`, `manage-brand-guidelines`.
- Events: `manage-events`, `upload-event-files`.
- AI and reporting: `use-ai`, `manage-ai-settings`, `view-reports`, `view-analytics`.

Permission identifiers are stable machine names. Add a permission through the catalog, both translation files, the default role map, policies for its resource, and tests in one reviewed change. The UI intentionally does not create, rename, delete, or directly grant permissions. Permissions are assigned through roles.

The idempotent `AccessControlSeeder` creates these initial roles:

| Role | Default permissions |
| --- | --- |
| Super Administrator | All 18 permissions |
| Administrator | All except `manage-permissions` |
| Content Manager | `manage-prompts`, `publish-prompts`, `manage-categories`, `manage-brand-guidelines`, `use-ai` |
| Event Manager | `manage-events`, `upload-event-files`, `use-ai` |
| Standard User | `use-ai` |

Existing users without a role receive Standard User when the seeder runs. Registration and Google-created accounts receive it when the catalog exists. No role grants access by role-name checks except protection of the reserved Super Administrator identity.

## Enforcement and delegation

The `/admin` group requires authentication, active status, verified email, and `access-admin`. Each controller authorizes read access through a policy. Every mutation passes through a recently-authenticated route and an Action that authorizes again, validates untrusted input, writes inside a transaction, and records a redacted account audit.

There is no `Gate::before` super-user bypass. A Super Administrator still needs explicit permissions and gains no implicit access to another user's private future content. UI visibility is only a convenience; policies and Actions are the security boundary.

A role can be assigned only when every permission in that role is already held by the actor. Permission assignment likewise rejects any capability the actor does not hold. Only an actor with both `manage-roles` and `manage-permissions` can change a role's permission set. A non-super actor cannot alter a user who holds the Super Administrator role, and the protected role itself cannot be renamed or edited.

Users cannot edit, disable, delete, or change their own roles through administration. Role assignment and status changes revoke existing sessions and increment the credential security version. Deletion is soft deletion with immediate credential revocation; it is not privacy erasure.

All operations that can remove an active Super Administrator lock the protected role row before locking and counting active super users. This serializes concurrent governance changes and prevents disabling, deleting, or demoting the last active Super Administrator.

## First administrator and operations

No production password or privileged account is seeded. On a new installation:

1. Run migrations and `php artisan db:seed --class=AccessControlSeeder`.
2. Register the intended operator through the normal application flow.
3. Run `php artisan auth:create-super-admin operator@example.com` locally on the trusted server.

The one-time action selects an existing account, makes it active and email-verified, assigns only the Super Administrator role, revokes prior sessions, and audits the promotion. It refuses to run once any active Super Administrator exists. Later super-role assignments happen only through the protected administration workflow.

The role and permission cache is cleared by the seeder and package mutation APIs. Queue jobs that depend on permissions must reload the user and authorize again when they execute. New resource modules must add policies and collection visibility rules; an administrative role alone must never reveal personal prompts, AI conversations, or private event content.
