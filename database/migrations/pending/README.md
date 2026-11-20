# Pending migrations (not run)

Laravel only runs migrations directly inside `database/migrations/`, so the files here are **never run** by
`php artisan migrate`.

- `2026_11_20_110000_create_fbr_tables.php`: FBR integration groundwork (fbr_settings, fbr_terminals,
  fbr_submissions, invoices.fbr_invoice_no / fbr_status). Parked on 2026-11-20 because FBR was postponed by the
  owner. It also copies the old `businesses.fbr_*` values and switches any business on FBR numbering back to local
  numbering. Move it up into `database/migrations/` (rename it with the current date) when step 6 resumes; see
  `docs/FBR_INTEGRATION.md`.
