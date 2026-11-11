# Security notes (Roadmap step 13)

## 13a. Secrets that were in git

`.env` was committed in the past and is still in the git history (4 commits). Since step 13a it is no longer tracked
(`.gitignore` lists it; each machine keeps its own copy; `setup.sh` creates one from `.env.example` when missing).

**Rotate these at the provider.** Anything that was ever in `.env` in git must be treated as public:

| Key in `.env` | Where to rotate | Notes |
|---|---|---|
| `DB_PASSWORD` (and `DB_USERNAME`) | MySQL: `ALTER USER … IDENTIFIED BY …` | Then update every machine's `.env`. |
| `MAIL_USERNAME`, `MAIL_PASSWORD`, `SMTP_EMAIL`, `SMTP_PASSWORD` | The mail provider (app password) | `.env.example` also carried mail values; they are blanked now. |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | Stripe dashboard → Developers | Roll keys; re-create the webhook secret. |
| `SUPERADMINEMAIL`, `SUPERADMINPASSWORD` | The account itself | The `.env` login shortcut is gone (13b); change that account's password with `php artisan app:super-admin <email>`. Then delete these keys from `.env`. |
| `APP_KEY` | `php artisan key:generate` | **Last, and only with a plan:** it decrypts saved FBR API tokens and every session. After rotating, owners re-enter FBR tokens and everyone signs in again. |
| `AWS_*`, `PUSHER_*` | Their providers | Only if they were ever filled in. |
| GitHub personal access token (pasted in a chat on 2026-11-06) | GitHub → Settings → Developer settings → Tokens | Revoke and create a new one. |

The git history is **not** rewritten (that needs a force-push and every teammate to re-clone; product owner's call).

## 13b. Super admin

There is no `.env` login shortcut any more. A super admin is a normal account:

```
php artisan app:super-admin you@example.com          # create, or promote and set a new password
php artisan app:super-admin you@example.com --keep-password
```

`UserSeeder` no longer creates `admin@mail.com` / `password`.

## 13e. Rate limits and security review (done 2026-11-11)

Fixed from the independent review:
- **Escalation:** a member can't give anyone a role with more permissions than they hold, can't change their own role or
  status, and the business always keeps one active owner (Team, Roles).
- **Suspended members** can't enter or switch to that business; suspended in every business = signed out with a message.
- **Lookups** (`/admin/lookup/{list}`) need a permission for that list (suppliers: catalogue or purchases; stock items:
  POS, purchases or catalogue; generic lists: generic import).
- **Super admin** outside a business gets 409 on invoice / return numbers (numbers are per business): open the
  business first (Platform → Businesses → Open).
- Reset codes: attempts counted atomically (no race past the 5-try limit). SMTP settings refuse private / loopback hosts
  and unusual ports. Logout invalidates the session and the CSRF token. `/qr-logo` removed. Inertia devtools off by
  default.
- `AuthenticateSession`: a password change signs the account out on every other device.
- Security headers on every response: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, HSTS when served over HTTPS.

Rate limits (per user or IP, per minute unless stated): sign-in 20 (+ 5 tries per email+IP), sign-up 6, reset 6–10,
lookup 120, POS quote 180, checkout 40, returns 30, return preview 60, team invite 20, test email 6, password change 6,
billing subscribe/change 10, backups 3, data export 5 per hour, Stripe webhook 120.

Production cookies (`.env`): `SESSION_SECURE_COOKIE=true` on HTTPS; cookies are always `HttpOnly`, `SameSite=Lax`.

## 13h. `bec_*` tables

Ten tables of an email-campaign tool live in the same database (`bec_activity_logs`, `bec_campaign_logs`,
`bec_campaigns`, `bec_contact_list_columns`, `bec_contact_lists`, `bec_contacts`, `bec_duplicate_contacts`,
`bec_email_templates`, `bec_signatures`, `bec_smtp_settings`; 79 rows on 2026-11-06). No code in this project reads or
writes them, and no migration creates them. `bec_smtp_settings` likely holds mail credentials.

**Recommendation:** export them (`mysqldump db bec_% > bec_backup.sql`), confirm with whoever owns that tool, then drop
them with a migration. Until the product owner says yes, they are left untouched.
