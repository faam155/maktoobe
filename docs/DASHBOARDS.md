# Dashboard conventions

Phase 4 provides separate Blade layouts and dashboard routes for the authenticated user portal (`/app`) and permission-protected administration area (`/admin`). Both route groups continue to require an active, email-verified account. The admin group additionally requires `access-admin`.

Controllers remain thin and delegate dashboard composition to `App\Queries\Dashboard`. `PortalDashboardQuery` exposes only capability-appropriate sections and quick actions. `AdminDashboardQuery` authorizes `access-admin` itself, applies granular permission checks before every aggregate, and exposes only redacted account-audit summaries. Future modules replace their unavailable entries through these query boundaries when their schemas and policies exist.

Navigation visibility is a usability aid. Every implemented destination has server-side middleware, policies or Gates. Unimplemented destinations are rendered as non-interactive unavailable items, never guessed routes. Their metric cards contain no fabricated zero counts. User totals and recent account activity are real database results and are shown only to actors with `manage-users`.

The portal uses a persistent sidebar on desktop and a native compact disclosure menu on mobile. English sets LTR and Arabic sets RTL through the shared locale middleware and document attributes. CSS uses logical properties, responsive grids and touch-sized controls. Dashboard browser coverage includes 1440×900, 1280×800, 768×1024, 390×844 and 360px.
