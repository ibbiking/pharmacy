<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies one business and the generic medicine catalogue from another database (already migrated to this app's
 * schema) into this one, with new ids (owner 2026-11-20: RANA PHARMACY from pharmacy_latest, docs/HANDOFF_RANA_IMPORT.md).
 *
 *  - Generic catalogue: merged by name (companies, formulas, strengths, categories, types; products by name + company
 *    + strength + type), so businesses already linked to it keep their links. Only missing rows are added.
 *  - The business: every table in TABLES is copied with its *_id columns remapped. The new business gets this app's
 *    defaults (warehouse, chart of accounts, roles, number series, modules of its type) and the owner given.
 *
 *   php artisan app:import-business --from=rana_import --business=1 --owner=owner@example.com [--owner-from=...]
 * Refuses when the target already has a business with that name for that owner (run it once).
 */
class ImportBusiness extends Command
{
    protected $signature = 'app:import-business
        {--from= : Source database (same server, this app\'s schema)}
        {--business=1 : Source business id}
        {--owner= : Email of the owner in this database}
        {--owner-from= : Copy the owner account (name, email, password hash) from this database if it is missing here}
        {--name= : Business name here (default: the source name)}
        {--generics-only : Only merge the generic catalogue}
        {--relink= : Id of a business here imported earlier from --business: point its products at the right generic products again}';

    protected $description = 'Copy a business and the generic catalogue from another database, with new ids';

    /** Generic lookups merged by name. */
    private const GENERIC_LOOKUPS = ['generic_companies', 'generic_farmulas', 'generic_strengths', 'generic_categories', 'generic_product_types'];

    /**
     * Business tables in copy order => [column => table whose new id it takes]. 'self' = business lookups.
     * Columns not listed are copied as they are (business_id is always the new business).
     */
    private const TABLES = [
        'categories' => [], 'companies' => [], 'farmulas' => [], 'strengths' => [], 'product_types' => [], 'suppliers' => [], 'pharmacies' => [],
        'preferences' => [],
        'products' => ['company_id' => 'companies', 'farmula_id' => 'farmulas', 'product_type_id' => 'product_types', 'strength_id' => 'strengths',
            'sale_price_preference_id' => 'product_preferences', 'generic_product_id' => 'generic_products', 'min_qty_category_id' => 'categories'],
        'product_parameters' => ['product_id' => 'products', 'category_id' => 'categories', 'parent_category_id' => 'categories', 'child_category_id' => 'categories'],
        'product_categories' => ['product_id' => 'products', 'parent_category_id' => 'categories', 'child_category_id' => 'categories'],
        'purchases' => ['product_id' => 'products', 'category_id' => 'categories', 'base_category_id' => 'categories', 'supplier_id' => 'suppliers', 'warehouse_id' => 'warehouses', 'document_id' => null],
        'product_stock' => ['product_id' => 'products', 'base_category_id' => 'categories'],
        'stock_prices' => ['purchase_id' => 'purchases', 'product_id' => 'products', 'category_id' => 'categories', 'base_category_id' => 'categories'],
        'base_stock_sale_price' => ['purchase_id' => 'purchases', 'product_id' => 'products', 'warehouse_id' => 'warehouses', 'category_id' => 'categories', 'base_category_id' => 'categories'],
        'invoices' => ['customer_id' => null],
        'invoice_items' => ['invoice_id' => 'invoices', 'product_id' => 'products', 'category_id' => 'categories', 'base_category_id' => 'categories'],
        'invoice_item_returns' => ['invoice_id' => 'invoices', 'invoice_item_id' => 'invoice_items', 'product_id' => 'products'],
    ];

    /** @var array<string, array<int, int>> table => [old id => new id] */
    private array $map = [];

    public function handle(): int
    {
        $from = (string) $this->option('from');
        if ($from === '' || $from === DB::getDatabaseName()) {
            $this->error('Give --from=<another database>.');

            return self::FAILURE;
        }
        config(['database.connections.import' => array_merge(config('database.connections.'.config('database.default')), ['database' => $from])]);
        $src = DB::connection('import');

        DB::transaction(fn () => $this->mergeGenerics($src));
        if ($this->option('generics-only')) {
            return self::SUCCESS;
        }
        if ($this->option('relink')) {
            return $this->relink($src, (int) $this->option('business'), (int) $this->option('relink'));
        }

        $sourceBusiness = $src->table('businesses')->find((int) $this->option('business'));
        if (! $sourceBusiness) {
            $this->error('Source business not found.');

            return self::FAILURE;
        }
        $owner = $this->owner($src);
        if (! $owner) {
            return self::FAILURE;
        }
        $name = (string) ($this->option('name') ?: $sourceBusiness->name);
        if ($owner->businesses()->where('name', $name)->exists()) {
            $this->error("{$owner->email} already has a business named “{$name}”: nothing copied.");

            return self::FAILURE;
        }

        $this->mapGlobalProductPreferences($src);
        $business = $this->createBusiness($sourceBusiness, $name, $owner);
        DB::transaction(function () use ($src, $sourceBusiness, $business) {
            foreach (self::TABLES as $table => $refs) {
                $this->copyTable($src, $table, $refs, (int) $sourceBusiness->id, $business->id);
            }
        });
        $this->finish($business, $owner);

        foreach (array_keys(self::TABLES) as $table) {
            $this->line(sprintf('  %-22s %d', $table, count($this->map[$table] ?? [])));
        }
        $this->info("“{$business->name}” (id {$business->id}) is ready for {$owner->email}.");

        return self::SUCCESS;
    }

    private function mergeGenerics($src): void
    {
        foreach (self::GENERIC_LOOKUPS as $table) {
            $here = DB::table($table)->get(['id', 'name'])->mapWithKeys(fn ($r) => [mb_strtolower(trim($r->name)) => $r->id])->all();
            $added = 0;
            foreach ($src->table($table)->get() as $row) {
                $key = mb_strtolower(trim($row->name));
                if (! isset($here[$key])) {
                    $data = (array) $row;
                    unset($data['id']);
                    $data['suggested_by_business_id'] = null;
                    $here[$key] = DB::table($table)->insertGetId($data);
                    $added++;
                }
                $this->map[$table][$row->id] = $here[$key];
            }
            $this->line(sprintf('  %-30s +%d', $table, $added));
        }

        // One source row = one row here: each source row takes over a matching row (same name, company, strength,
        // type, and archived or not) that existed before, else it is added. So duplicates and archived copies in the
        // source stay exactly as they are, and a second run adds nothing.
        $key = fn ($name, $company, $strength, $type, $deleted) => mb_strtolower(trim((string) $name)).'|'.$company.'|'.$strength.'|'.$type.'|'.($deleted ? 1 : 0);
        $here = [];
        foreach (DB::table('generic_products')->orderBy('id')->get(['id', 'product_name', 'generic_company_id', 'strength_id', 'generic_product_type_id', 'deleted_at']) as $p) {
            $here[$key($p->product_name, $p->generic_company_id, $p->strength_id, $p->generic_product_type_id, $p->deleted_at)][] = $p->id;
        }
        $added = [];
        foreach ($src->table('generic_products')->orderBy('id')->get() as $row) {
            $data = (array) $row;
            $data['generic_company_id'] = $this->mapped('generic_companies', $row->generic_company_id);
            $data['farmula_id'] = $this->mapped('generic_farmulas', $row->farmula_id);
            $data['strength_id'] = $this->mapped('generic_strengths', $row->strength_id);
            $data['generic_product_type_id'] = $this->mapped('generic_product_types', $row->generic_product_type_id);
            $k = $key($row->product_name, $data['generic_company_id'], $data['strength_id'], $data['generic_product_type_id'], $row->deleted_at);
            if (! empty($here[$k])) {
                $this->map['generic_products'][$row->id] = array_shift($here[$k]);
                continue;
            }
            unset($data['id']);
            $data['suggested_by_business_id'] = null;
            $data['approved_by'] = null;
            $this->map['generic_products'][$row->id] = DB::table('generic_products')->insertGetId($data);
            $added[$row->id] = true;
        }
        // Packaging of the generic products that were added (existing ones keep theirs).
        foreach (['generic_product_parameters' => ['generic_category_id', 'parent_generic_category_id', 'child_generic_category_id'],
            'generic_product_categories' => ['parent_generic_category_id', 'child_generic_category_id']] as $table => $cats) {
            foreach ($src->table($table)->whereIn('generic_product_id', array_keys($added))->orderBy('id')->get() as $row) {
                $data = (array) $row;
                unset($data['id']);
                $data['generic_product_id'] = $this->map['generic_products'][$row->generic_product_id];
                foreach ($cats as $c) {
                    $data[$c] = $this->mapped('generic_categories', $row->$c);
                }
                DB::table($table)->insert($data);
            }
        }
        $this->line(sprintf('  %-30s +%d (of %d)', 'generic_products', count($added), count($this->map['generic_products'] ?? [])));
    }

    /** Product-level sale price choices are global rows (business_id null): matched by type + slug, added if missing. */
    private function mapGlobalProductPreferences($src): void
    {
        $here = DB::table('product_preferences')->whereNull('business_id')->get()->mapWithKeys(fn ($p) => [$p->type.'|'.$p->slug => $p->id])->all();
        foreach ($src->table('product_preferences')->whereNull('business_id')->get() as $row) {
            $k = $row->type.'|'.$row->slug;
            if (! isset($here[$k])) {
                $data = (array) $row;
                unset($data['id']);
                $here[$k] = DB::table('product_preferences')->insertGetId($data);
            }
            $this->map['product_preferences'][$row->id] = $here[$k];
        }
    }

    /**
     * Repairs generic links of a business copied earlier: each product here is found by its source product's name and
     * creation time (both copied as they were) and linked to the generic product its source product uses.
     */
    private function relink($src, int $fromBusiness, int $toBusiness): int
    {
        $here = DB::table('products')->where('business_id', $toBusiness)->get(['id', 'product_name', 'created_at', 'generic_product_id'])
            ->groupBy(fn ($p) => mb_strtolower(trim((string) $p->product_name)).'|'.$p->created_at);
        $fixed = $missing = 0;
        foreach ($src->table('products')->where('business_id', $fromBusiness)->orderBy('id')->get(['product_name', 'created_at', 'generic_product_id']) as $row) {
            $match = $here->get(mb_strtolower(trim((string) $row->product_name)).'|'.$row->created_at)?->shift();
            if (! $match) {
                $missing++;
                continue;
            }
            $generic = $row->generic_product_id === null ? null : $this->mapped('generic_products', $row->generic_product_id);
            if ((int) $match->generic_product_id !== (int) $generic) {
                DB::table('products')->where('id', $match->id)->update(['generic_product_id' => $generic]);
                $fixed++;
            }
        }
        $this->info("Relinked {$fixed} product(s); {$missing} source product(s) not found here.");

        return $missing ? self::FAILURE : self::SUCCESS;
    }

    private function owner($src): ?User
    {
        $email = mb_strtolower(trim((string) $this->option('owner')));
        $user = User::query()->where('email', $email)->first();
        if (! $user && $this->option('owner-from')) {
            config(['database.connections.owner_src' => array_merge(config('database.connections.'.config('database.default')), ['database' => $this->option('owner-from')])]);
            $row = DB::connection('owner_src')->table('users')->where('email', $email)->first();
            if ($row) {
                $user = new User(['name' => $row->name, 'email' => $row->email]);
                $user->forceFill(['password' => $row->password, 'email_verified_at' => $row->email_verified_at ?? now(), 'theme' => 'dark'])->save();
                $this->line("  owner account {$email} copied from {$this->option('owner-from')}");
            }
        }
        if (! $user) {
            $this->error("No account {$email} here (give --owner-from=<database> to copy it).");
        }

        return $user;
    }

    private function createBusiness(object $source, string $name, User $owner): Business
    {
        \Spatie\Permission\Models\Role::findOrCreate('Business Owner');
        if (! $owner->hasRole('Business Owner')) {
            $owner->assignRole('Business Owner');
        }
        $data = collect((array) $source)->only(['phone', 'address', 'email', 'logo', 'currency_id', 'invoice_note', 'note'])
            ->filter(fn ($v, $k) => Schema::hasColumn('businesses', $k))->all();
        // Created through the model: number series, built-in roles and the type's modules come with it.
        $business = Business::create($data + ['name' => $name, 'business_type' => $source->business_type ?: 'pharmacy', 'created_by' => $owner->id]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);
        session(['business_id' => $business->id]);
        $this->map['warehouses'] = [];
        // The business's own default warehouse: every copied stock row is placed there.
        $this->defaultWarehouse = app(\App\Services\Stock\WarehouseService::class)->ensureDefault($business->id);
        if (DB::table('warehouses')->where('id', $this->defaultWarehouse)->value('business_id') != $business->id) {
            throw new \RuntimeException('The default warehouse does not belong to the new business.');
        }

        return $business;
    }

    private int $defaultWarehouse = 0;

    private function copyTable($src, string $table, array $refs, int $fromBusiness, int $toBusiness): void
    {
        if (! Schema::hasTable($table) || ! $src->getSchemaBuilder()->hasTable($table)) {
            return;
        }
        $columns = array_flip(Schema::getColumnListing($table));
        // A preference the new business already has (made with the business) is reused, not duplicated.
        $existing = $table === 'preferences'
            ? DB::table('preferences')->where('business_id', $toBusiness)->get(['id', 'type', 'slug'])->mapWithKeys(fn ($p) => [$p->type.'|'.$p->slug => $p->id])->all() : [];

        foreach ($src->table($table)->where('business_id', $fromBusiness)->orderBy('id')->get() as $row) {
            $data = array_intersect_key((array) $row, $columns);
            $old = (int) $data['id'];
            unset($data['id']);
            $data['business_id'] = $toBusiness;
            foreach ($refs as $column => $target) {
                if (! array_key_exists($column, $data)) {
                    continue;
                }
                $data[$column] = match (true) {
                    $target === null => null,
                    $target === 'warehouses' => $this->defaultWarehouse ?: null,
                    default => $this->mapped($target, $data[$column]),
                };
            }
            if ($table === 'preferences' && isset($existing[$row->type.'|'.$row->slug])) {
                $id = $existing[$row->type.'|'.$row->slug];
                DB::table('preferences')->where('id', $id)->update(array_diff_key($data, ['created_at' => 1]));
                $this->map[$table][$old] = $id;
                continue;
            }
            $this->map[$table][$old] = DB::table($table)->insertGetId($data);
        }
    }

    private function mapped(string $table, $old): ?int
    {
        if ($old === null) {
            return null;
        }
        if (! isset($this->map[$table][(int) $old])) {
            throw new \RuntimeException("No new id for {$table} #{$old} (copy order or missing row).");
        }

        return $this->map[$table][(int) $old];
    }

    private function finish(Business $business, User $owner): void
    {
        app(\App\Services\Accounting\AccountingService::class)->ensureChart($business->id);
        app(\App\Services\Expense\ExpenseService::class)->ensureDefaults($business->id);
        app(\App\Services\Access\PermissionService::class)->ensureSystemRoles($business->id);
        app(\App\Services\Numbering\NumberSeriesService::class)->ensureDefaults($business->id);
        $modules = app(\App\Services\Modules\ModuleManager::class);
        foreach ($modules->bundleFor($business->business_type) as $m) {
            if ($m !== 'core' && ($modules->definition($m)['available'] ?? true) && ! $modules->isActive($business->id, $m)) {
                $modules->activate($business, $m, 'free', $owner->id);
            }
        }
        app(\App\Services\Accounting\LedgerPoster::class)->backfill($business->id);
    }
}
