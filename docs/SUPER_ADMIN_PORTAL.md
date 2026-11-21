# Super admin portal (separate platform area)

Owner's request (2026-11-21, verbatim): "superadmin must have another url, another overall complete other directory, the
code and every thing must be different just using the components from already services, but the views and all layouts
and code and all other things related to db if required other tables must all and everything should be different … you
can see its example in see ats project … super admin access of all business types, of all businesses and all data and
rights must be perfect in flow … needed everything live, local and github, send email".

**Status:** approved by the owner 2026-11-21. Progress is ticked below; resume from the first unticked item.

## Why

Today the super admin is a normal `users` row with the Spatie role `super-admin`, signs in on the business login
(`/admin/login`), and works inside the business app (`/admin/superadmin/*`, 21 routes, 9 pages in
`Pages/SuperAdmin`). `BusinessScope`, `Navigation` and several middleware/controllers have `hasRole('super-admin')`
branches. The owner wants the platform side fully separate, like the see-ats project.

## Pattern (from see-ats, adapted to this session-based Inertia app)

| Part | Business app (unchanged) | Platform portal (new) |
|---|---|---|
| URL | `/admin/*` | **`/platform/*`** (login `/platform/login`) |
| Who | `users` + business pivot | **`platform_admins`** table, model `App\Models\Platform\PlatformAdmin` |
| Guard | `web` | **`platform`** (session driver, provider `platform_admins`) |
| Middleware | `EnsureUserHasBusiness`, permissions, modules | **`platform.auth`** (`EnsurePlatformAdmin`), none of the business middleware |
| Routes | `routes/web.php`, `routes/modules.php` | **`routes/platform.php`** |
| Controllers | `app/Http/Controllers/Admin/*` | **`app/Http/Controllers/Platform/*`** |
| Pages / layout | `Pages/*`, `Layouts/AppLayout` | **`Pages/Platform/*`, `Layouts/PlatformLayout.tsx`**, root view `platform.blade.php` |
| Inertia shared props | `HandleInertiaRequests` | **`HandlePlatformInertiaRequests`** (admin, flash, platform navigation only) |
| Business data | `BusinessScope` (current business) | services queried **explicitly per business** (`withoutGlobalScope`) — no role bypass |
| Services | — | reused: ModuleManager, BillingService, DemoSandboxService, reports, backup |

Security: login throttled (5/min per email+IP), same error for unknown email / wrong password / archived admin, inactive
admin refused after a correct password, `last_login_at`, session regenerated, a platform admin can never use `/admin`
with that login (separate guard) and a business user can never use `/platform`. Platform admin rows: timestamps + soft
deletes; an admin can't archive or deactivate themselves.

## Steps

- [ ] **P1 Foundation.** Migration `platform_admins` (name, email unique among non-archived, password, is_active,
      last_login_at, remember_token, timestamps, softDeletes) + `platform_audit_logs` (append-only: admin, action,
      business_id, details, created_at). Data migration: copy every `users` row with role `super-admin` into
      `platform_admins` with the **same password hash** (owner's password keeps working). Guard/provider in
      `config/auth.php`, middleware alias, `routes/platform.php` registered in `bootstrap/app.php` (web group without
      `HandleInertiaRequests`, plus `HandlePlatformInertiaRequests`), root view, guest redirect to `/platform/login`.
      Command `platform:admin {email} --name= --password=` (replaces `app:super-admin` in `deploy/cpanel-install.sh`).
- [ ] **P2 Login + layout.** `Platform\AuthController` (login, logout), `Pages/Platform/Login.tsx`,
      `Layouts/PlatformLayout.tsx` (own sidebar: Dashboard, Businesses, People, Billing, Visits, Settings, Backups,
      Platform admins).
- [ ] **P3 Screens (new controllers + pages, reusing services).** Dashboard (businesses per type, people, sales today,
      sign-ups); Businesses (list with type filter + search, paged); Business detail (modules on/off/block,
      sub-modules, people, counts, owner); People (all users, their businesses and roles, paged); Billing (plans,
      module prices, subscriptions); Visits; App settings; Backups; Platform admins (add / deactivate / archive).
- [ ] **P4 Open a business with full rights.** "Open business" on the detail page: the platform admin enters the
      business app **as that business's owner** (web guard login of the owner, session flag `platform_admin_id`, a
      top bar "You are in <business> as platform admin — Back to platform"), every visit logged in
      `platform_audit_logs`. "Back to platform" signs the owner session out and returns to `/platform/businesses/{id}`.
      Works for every business type.
- [ ] **P5 Retire the old super admin.** `/admin/superadmin/*` and `/admin/backup*` redirect to `/platform/...`; remove
      the `super-admin` branches from `BusinessScope`, `Navigation`, middleware and controllers; the
      `SUPERADMINEMAIL` login bypass in `LoginController` is removed; old `Pages/SuperAdmin/*` deleted; tests moved to
      `tests/Feature/PlatformPortal/*`. The Spatie role row stays (harmless) until a later clean-up migration.
- [ ] **P6 Tests + docs.** Feature tests: guard separation both ways, login throttling, every platform screen, open
      business for each type and back, module block/unblock, data counts across businesses. Update CLAUDE.md,
      DEPLOYMENT.md, DEMO_ACCOUNTS.md, REMAINING.md, INSTALL_NOTES.txt.
- [ ] **P7 Live.** Commit + push, release to live and demo (`deploy/cpanel-release.sh both`, migrations run there),
      check `https://suite.alhamaad.com/platform/login` and the demo, health check, email the owner the new URL.

## Resume notes

- Health-check script used before releases: a rolled-back crawl of every screen + POS sale/return per business and a
  full sign-up (see docs/DEMO_ACCOUNTS.md "On the internet"). Extend it with a `--platform` mode in P6.
- Owner's email for the summary: send through Gmail SMTP **from the dev laptop** (GoDaddy shared hosting blocks
  outgoing Gmail ports 587/465); the app password is not stored in the repo.
