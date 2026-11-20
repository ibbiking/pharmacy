# ✅ Done 2026-11-21: RANA PHARMACY + generic catalogue everywhere

Result: `php artisan app:import-business` (app/Console/Commands/ImportBusiness.php).
- Generic catalogue = all 1210 rows of pharmacy_latest (1199 active, 11 archived, with packaging), copied 1:1 and
  merged by name into: local pharmacy_laravel13 (total 1222: it had a few extra) and pharmacy_qa (1219), live (1210),
  demo (1210; the nightly demo reset does not touch the catalogue).
- RANA PHARMACY (pharmacy, owner Mohid Awais mohidawaiskhan@gmail.com, his password copied) with 1211 products and
  every related row, in local pharmacy_laravel13 and live invora (id 1). Product → generic links verified identical to
  the source on live. Rana Medical Store and the other local dev businesses are untouched.
- Backups before the import: ../db_backups/*_before_rana_2026-11-21_0152.sql.gz (local), ~/invora_*_before_rana_* (server).

---

# (History) Paused task: RANA PHARMACY + generic catalogue everywhere (2026-11-20)

## The owner's request (verbatim)
> do we have all the generic products in? and settings that we have had in mohidawais business in RANA PHARMACY?
> pharmacy_latest in local have all these 1210 products, needed the same RANA PHARMACY setup and data for pharmacy
> business type in the database on local, github and live, the db you are using everywhere. this is what i forget to
> mention, please do it. can simply change the ids of record, no problem with that

Then: "please stop right now on safe place … when i will come again i will ask you to continue".

## What was found
- `pharmacy_latest` (local MySQL, old schema, 50 migrations): business **RANA PHARMACY** (id 1) with products 1211,
  product_parameters 3119, product_categories 2105, categories 31, companies 153, farmulas 441, strengths 213,
  product_types 36, suppliers 21, purchases 7, product_stock 7, stock_prices 7, base_stock_sale_price 7, invoices 2,
  invoice_items 4, invoice_item_returns 4, preferences 4, product_preferences 4, pharmacies 1; global generic catalogue:
  generic_products 1210, generic_product_parameters 2165, generic_product_categories 1431, generic_companies 153,
  generic_farmulas 441, generic_strengths 212, generic_categories 27, generic_product_types 33.
- `pharmacy_laravel13` (local main DB): Mohid Awais `mohidawaiskhan@gmail.com` (user 1) owns "Rana Medical Store"
  (768 products) + 3 other dev pharmacies; only 772 generic products. Live (`invora.alhamaad.com`) and demo have none.

## Plan (agreed: ids may change)
1. ✅ Backup `../db_backups/pharmacy_latest_<stamp>.sql.gz`; scratch copy **`rana_import`** = pharmacy_latest upgraded
   with today's migrations (`DB_DATABASE=rana_import php artisan migrate --force`, ran cleanly; business_type
   pharmacy, 6 modules; **0 warehouses** → run the ensure* defaults after import).
2. ⏳ Write an import command (e.g. `app:import-business`): from connection `rana_import`
   - merge the generic catalogue by name (generic companies / farmulas / strengths / categories / types / products /
     parameters / product categories) so existing businesses keep their links; new rows get new ids;
   - copy every business_id table of business 1 into a NEW pharmacy business "RANA PHARMACY" owned by Mohid Awais
     (create the user on live from the local row incl. its password hash), remapping ids and *_id columns;
   - then WarehouseService::ensureDefault, AccountingService::ensureChart, ExpenseService::ensureDefaults,
     PermissionService::ensureSystemRoles, NumberSeriesService::ensureDefaults, modules = bundle for pharmacy,
     LedgerPoster::backfill; check stock / product counts match the source.
3. ⏳ Run into local `pharmacy_laravel13` (backup first), test in the browser, then live (`invora_live`) via
   deploy/cpanel-release.sh + the command on the server; generic catalogue also into demo + `pharmacy_qa`
   (demo: also add to DemoDataSeeder / rebuild so the nightly reset keeps it).
4. ⏳ Refresh the private backup repo `ibbiking/invora-db-backups` (local + live dumps), commit + push code.

## Still waiting on the owner (from the deployment)
- Add domains in cPanel: invora.alhamaad.com → `invora.alhamaad.com/public`, invora-demo.alhamaad.com →
  `invora-demo.alhamaad.com/public` (Share document root unticked). Then test from outside.
- Content on live: real data or clean? Merge the branch into main?
