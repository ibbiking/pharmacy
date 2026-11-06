<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Models\BaseStockSalePrice;
use App\Models\Category;
use App\Models\Farmula;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Strength;
use App\Services\Catalogue\ProductFormService;
use App\Services\Catalogue\ProductSetupService;
use App\Services\Sales\PackagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Products (Inertia, Roadmap 5.4a/5.4c): one list (all / drafts / in stock / out of stock / expired stock /
 * expiring soon) using the real lot stock, the product page (batches, packaging, prices, sales) and the setup
 * wizard (packaging chain + unit prices). Stock = remaining base units of lots that are not expired
 * (LotAllocator's "sellable" rule); expired stock = remaining base units of lots past expiry.
 */
class ProductCatalogController extends Controller
{
    private const STATUSES = ['drafts', 'in_stock', 'out', 'expired', 'expiring', 'archived'];

    public function __construct(private readonly ProductSetupService $setup, private readonly PackagingService $packaging, private readonly ProductFormService $form)
    {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $sort = in_array($request->input('sort'), ['product_name', 'stock', 'next_expiry', 'created_at'], true) ? $request->input('sort') : 'product_name';
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $status = in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : null;

        $query = $this->withStock($status === 'archived' ? Product::onlyTrashed() : Product::query())
            ->with(['company:id,name', 'type:id,name'])
            ->when($status === 'archived', fn (Builder $w) => $w, fn (Builder $w) => $w->when($status === 'drafts', fn (Builder $w) => $w->where('is_draft', true), fn (Builder $w) => $w->where('is_draft', false)))
            ->when($q !== '', fn (Builder $w) => $w->where(fn ($x) => $x->where('products.product_name', 'like', "%{$q}%")->orWhere('products.barcode', $q)
                ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($status === 'in_stock', fn (Builder $w) => $w->having('stock', '>', 0))
            ->when($status === 'out', fn (Builder $w) => $w->having('stock', '<=', 0))
            ->when($status === 'expired', fn (Builder $w) => $w->having('expired_stock', '>', 0))
            ->when($status === 'expiring', fn (Builder $w) => $w->havingRaw('next_expiry is not null and next_expiry <= ?', [today()->addDays(90)->toDateString()]));

        $sortColumn = match ($sort) {
            'stock', 'next_expiry' => $sort,
            default => "products.{$sort}",
        };
        $page = $query->orderByRaw($sort === 'next_expiry' ? 'next_expiry is null' : '1')->orderBy($sortColumn, $dir)->orderBy('products.id')
            ->paginate($perPage)->withQueryString();

        $rows = $page->getCollection();
        $names = $this->names($rows);
        $baseUnits = Category::withTrashed()->whereIn('id', $rows->pluck('base_category_id')->filter())->pluck('name', 'id');
        $page->setCollection($rows->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->product_name,
            'strengths' => $this->pick($names['strengths'], $p->strength_id),
            'formulas' => $this->pick($names['formulas'], $p->farmula_id),
            'company' => $p->company?->name,
            'type' => $p->type?->name,
            'barcode' => $p->barcode,
            'stock' => round((float) $p->stock, 2),
            'expired_stock' => round((float) $p->expired_stock, 2),
            'unit' => $baseUnits[$p->base_category_id] ?? null,
            'next_expiry' => $p->next_expiry ? Carbon::parse($p->next_expiry)->format('d M Y') : null,
            'expiry_days' => $p->next_expiry ? (int) today()->diffInDays(Carbon::parse($p->next_expiry), false) : null,
            'draft' => (bool) $p->is_draft,
            'archived' => $p->trashed(),
        ]));

        return Inertia::render('Products/Index', [
            'rows' => $page,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => $this->stats(),
            'canManage' => $request->user()->can('core.catalogue.manage'),
            'canBuy' => $request->user()->can('stock.purchases.manage'),
        ]);
    }

    public function show(Request $request, $id)
    {
        $product = $this->withStock(Product::withTrashed())->with(['company:id,name', 'type:id,name'])->whereKey($id)->firstOrFail();
        $names = $this->names(collect([$product]));
        $chain = $this->setup->chain($product);
        $base = collect($chain)->last();

        $lots = BaseStockSalePrice::query()->where('product_id', $product->id)->where('remaining_base_stock', '>', 0)
            ->with('purchase:id,batch_no,supplier_id,created_at,unit_cost_price', 'purchase.supplier:id,name')
            ->orderByRaw('expiry_date is null')->orderBy('expiry_date')->orderBy('id')->limit(50)->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'batch' => $l->purchase?->batch_no,
                'supplier' => $l->purchase?->supplier?->name,
                'remaining' => (float) $l->remaining_base_stock,
                'received' => (float) $l->base_stock,
                'expiry' => $l->expiry_date ? Carbon::parse($l->expiry_date)->format('d M Y') : null,
                'days' => $l->expiry_date ? (int) today()->diffInDays(Carbon::parse($l->expiry_date), false) : null,
                'sale_price' => (float) $l->base_category_unit_sale_price,
                'date' => $l->purchase?->created_at?->format('d M Y'),
            ]);

        $sold30 = (float) InvoiceItem::query()->where('product_id', $product->id)->where('created_at', '>=', now()->subDays(30))->sum('row_total');

        return Inertia::render('Products/Show', [
            'product' => [
                'id' => $product->id,
                'name' => $product->product_name,
                'strengths' => $this->pick($names['strengths'], $product->strength_id),
                'formulas' => $this->pick($names['formulas'], $product->farmula_id),
                'company' => $product->company?->name,
                'type' => $product->type?->name,
                'barcode' => $product->barcode,
                'rack' => $product->rack,
                'description' => $product->description,
                'draft' => (bool) $product->is_draft,
                'archived' => $product->trashed(),
                'stock' => round((float) $product->stock, 2),
                'expired_stock' => round((float) $product->expired_stock, 2),
                'unit' => $base['name'] ?? null,
                'next_expiry' => $product->next_expiry ? Carbon::parse($product->next_expiry)->format('d M Y') : null,
                'sold_30d' => round($sold30, 2),
                'created_at' => $product->created_at?->format('d M Y'),
            ],
            'chain' => $chain,
            'lots' => $lots,
            'currency' => currency_symbol(),
            'locked' => $this->setup->lockReason($product),
            'canManage' => $request->user()->can('core.catalogue.manage'),
            'canBuy' => $request->user()->can('stock.purchases.manage'),
        ]);
    }

    /** The setup wizard: packaging chain + unit prices (opens after adding a product, or from the product page). */
    public function setup(Request $request, $product)
    {
        $product = Product::query()->whereKey($product)->firstOrFail();

        return Inertia::render('Products/Setup', [
            'product' => ['id' => $product->id, 'name' => $product->product_name, 'draft' => (bool) $product->is_draft],
            'chain' => $this->setup->chain($product),
            'locked' => $this->setup->lockReason($product),
            'currency' => currency_symbol(),
        ]);
    }

    public function saveSetup(Request $request, $product)
    {
        $product = Product::query()->whereKey($product)->firstOrFail();
        $wasDraft = (bool) $product->is_draft;
        $request->validate(['levels' => ['required', 'array', 'min:1', 'max:6']]);

        $this->setup->save($product, $request->input('levels'));

        return redirect()->route('products.show', $product->id)->with('success', $wasDraft
            ? "“{$product->product_name}” is ready. Record a purchase to put it in stock."
            : 'Packaging and prices saved.');
    }

    public function create(Request $request)
    {
        return Inertia::render('Products/Form', ['product' => null, 'locked' => null]);
    }

    public function store(Request $request)
    {
        $product = $this->form->save(null, $request->all());

        // Next: the setup wizard (packaging chain + unit prices) turns the draft into a sellable product.
        return redirect()->route('products.setup-wizard', $product->id)->with('success', "“{$product->product_name}” saved as a draft. Set up its packaging to finish.");
    }

    public function edit(Request $request, $product)
    {
        $product = Product::query()->with(['company:id,name', 'type:id,name'])->whereKey($product)->firstOrFail();
        $option = fn ($m) => $m ? ['value' => $m->id, 'label' => $m->name] : null;
        $many = fn (string $model, ?string $csv) => collect(array_filter(array_map('intval', explode(',', (string) $csv))))
            ->map(fn ($id) => $model::withTrashed()->find($id))->filter()->map($option)->values();

        return Inertia::render('Products/Form', [
            'product' => [
                'id' => $product->id,
                'product_name' => $product->product_name,
                'company' => $option($product->company),
                'product_type' => $option($product->type),
                'strengths' => $many(Strength::class, $product->strength_id),
                'formulas' => $many(Farmula::class, $product->farmula_id),
                'barcode' => $product->barcode,
                'rack' => $product->rack,
                'description' => $product->description,
                'lock_max_discount' => (bool) $product->lock_max_discount,
                'discount' => (float) $product->discount ?: null,
                'discount_percent' => (float) $product->discount_percent ?: null,
                'draft' => (bool) $product->is_draft,
            ],
            'locked' => $this->setup->lockReason($product),
        ]);
    }

    public function update(Request $request, $product)
    {
        $product = $this->form->save(Product::query()->whereKey($product)->firstOrFail(), $request->all());

        return redirect()->route('products.show', $product->id)->with('success', "“{$product->product_name}” updated.");
    }

    public function destroy(Request $request, $product)
    {
        $product = Product::query()->whereKey($product)->firstOrFail();
        if ($reason = $this->form->archiveBlocker($product)) {
            return back()->with('error', $reason);
        }
        $product->delete();

        return redirect()->route('products.index')->with('success', "“{$product->product_name}” archived. Past invoices keep it; restore it from the Archived filter.");
    }

    public function restore(Request $request, $product)
    {
        $product = Product::onlyTrashed()->whereKey($product)->firstOrFail();
        if ($reason = $this->form->restoreBlocker($product)) {
            return back()->with('error', $reason);
        }
        $product->restore();

        return back()->with('success', "“{$product->product_name}” restored.");
    }

    /** Old list links. */
    public function drafts()
    {
        return redirect()->route('products.index', ['status' => 'drafts']);
    }

    public function outstock()
    {
        return redirect()->route('products.index', ['status' => 'out']);
    }

    public function expired()
    {
        return redirect()->route('products.index', ['status' => 'expired']);
    }

    public function details($id)
    {
        return redirect()->route('products.show', $id);
    }

    private function withStock(Builder $query): Builder
    {
        $today = today()->toDateString();
        $lots = fn () => BaseStockSalePrice::query()->whereColumn('base_stock_sale_price.product_id', 'products.id')->where('remaining_base_stock', '>', 0);

        return $query->select('products.*')
            ->selectSub($lots()->where(fn ($w) => $w->whereNull('expiry_date')->orWhereDate('expiry_date', '>', $today))->selectRaw('coalesce(sum(remaining_base_stock), 0)'), 'stock')
            ->selectSub($lots()->whereDate('expiry_date', '<=', $today)->selectRaw('coalesce(sum(remaining_base_stock), 0)'), 'expired_stock')
            ->selectSub($lots()->whereDate('expiry_date', '>', $today)->selectRaw('min(expiry_date)'), 'next_expiry')
            ->selectSub(\App\Models\ProductParameter::query()->whereColumn('product_parameters.product_id', 'products.id')
                ->whereNotExists(fn ($w) => $w->selectRaw('1')->from('product_parameters as c')->whereColumn('c.product_id', 'product_parameters.product_id')
                    ->whereColumn('c.parent_category_id', 'product_parameters.child_category_id')->whereColumn('c.child_category_id', '!=', 'c.parent_category_id')->whereNull('c.deleted_at'))
                ->select('child_category_id')->limit(1), 'base_category_id');
    }

    private function stats(): array
    {
        $count = fn (?string $status) => $this->withStock(Product::query())
            ->when($status === 'drafts', fn ($w) => $w->where('is_draft', true), fn ($w) => $w->where('is_draft', false))
            ->when($status === 'in_stock', fn ($w) => $w->having('stock', '>', 0))
            ->when($status === 'out', fn ($w) => $w->having('stock', '<=', 0))
            ->when($status === 'expired', fn ($w) => $w->having('expired_stock', '>', 0))
            ->when($status === 'expiring', fn ($w) => $w->havingRaw('next_expiry is not null and next_expiry <= ?', [today()->addDays(90)->toDateString()]))
            ->toBase()->getCountForPagination();

        return [
            'total' => $count(null), 'drafts' => $count('drafts'), 'in_stock' => $count('in_stock'),
            'out' => $count('out'), 'expired' => $count('expired'), 'expiring' => $count('expiring'),
            'archived' => Product::onlyTrashed()->count(),
        ];
    }

    /** Names for the comma-separated strength / formula ids of a page of products (two queries). */
    private function names($products): array
    {
        $ids = fn (string $col) => $products->flatMap(fn ($p) => array_filter(array_map('intval', explode(',', (string) $p->{$col}))))->unique()->values();

        return [
            'strengths' => Strength::withTrashed()->whereIn('id', $ids('strength_id'))->pluck('name', 'id'),
            'formulas' => Farmula::withTrashed()->whereIn('id', $ids('farmula_id'))->pluck('name', 'id'),
        ];
    }

    private function pick($names, ?string $csv): array
    {
        return array_values(array_filter(array_map(fn ($id) => $names[(int) $id] ?? null, explode(',', (string) $csv))));
    }
}
