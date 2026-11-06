<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Tax;
use App\Models\ProductParameter;
use App\Models\ProductStock;
use App\Models\StockPrices;
use App\Models\BaseStockSalePrice;
use App\Models\PurchaseTax;
use App\Models\SaleTax;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;
use Illuminate\Support\Carbon;

class PurchaseController extends Controller
{
    public function getPurchaseCategoryPrice(Request $request)
    {
        $request->validate(['product_id' => 'required|integer', 'category_id' => 'required|integer']);
        Product::query()->findOrFail($request->integer('product_id')); // this business only

        return response()->json($this->suggestPrices($request->integer('product_id'), $request->integer('category_id')));
    }

    /**
     * Data the purchase form needs for one product (Roadmap 5.5c): its units with base-unit counts and the suggested
     * cost / sale price per unit (static prices, or the previous purchase, following the product's preference).
     */
    public function productInfo(Request $request, $product)
    {
        $product = Product::query()->real()->findOrFail($product);
        $packaging = app(\App\Services\Sales\PackagingService::class);
        $units = $packaging->units($product->id);
        $names = Category::withTrashed()->whereIn('id', array_keys($units))->pluck('name', 'id');
        $last = Purchase::query()->where('product_id', $product->id)->latest('id')->first();

        return response()->json([
            'id' => $product->id,
            'name' => $product->product_name,
            'base_unit' => $names[$packaging->baseCategoryId($product->id)] ?? null,
            'units' => collect($units)->map(fn ($per, $id) => ['value' => (int) $id, 'label' => $names[$id] ?? '?', 'base_units' => (float) $per]
                + $this->suggestPrices($product->id, (int) $id))->values(),
            'last' => $last ? ['category_id' => (int) $last->category_id, 'supplier' => $last->supplier?->name, 'date' => $last->created_at?->format('d M Y')] : null,
        ]);
    }

    /** @return array{unit_cost_price: float, paid_unit_cost_price: float, unit_sale_price: float} */
    private function suggestPrices(int $productId, int $categoryId): array
    {
        try {
            return $this->suggestPricesFor($productId, $categoryId);
        } catch (\Throwable $e) {
            // A suggestion must never block a purchase: report it and let the user type the prices.
            report($e);

            return ['unit_cost_price' => 0.0, 'paid_unit_cost_price' => 0.0, 'unit_sale_price' => 0.0];
        }
    }

    private function suggestPricesFor(int $productId, int $categoryId): array
    {

        $productCtrl = app(\App\Http\Controllers\Admin\ProductController::class);
        $preferenceInfo = $productCtrl->getSalePricePreference($productId);
        $preferenceSlug = $preferenceInfo['preference']->slug;

        // Ignore stock-wise price for purchasing new stock, fallback to previous-inventory
        if ($preferenceSlug == 'stock-wise-price') {
            $preferenceSlug = 'previous-inventory-price';
        }

        $unit_cost_price = 0;
        $paid_unit_cost_price = 0;
        $unit_sale_price = 0;

        $param = \App\Models\ProductParameter::where('product_id', $productId)
            ->where('child_category_id', $categoryId)
            ->first();

        $staticCost = $param ? (float)($param->static_category_unit_purchase_price ?? 0) : 0;
        $staticSale = $param ? (float)($param->static_category_unit_sale_price ?? 0) : 0;

        if ($preferenceSlug == 'previous-inventory-price') {
            // Find VERY last purchase for this product regardless of category
            $lastPurchase = \App\Models\Purchase::where('product_id', $productId)
                ->latest('created_at')
                ->first();

            if ($lastPurchase) {
                // If the selected category is exactly the one in last purchase
                if ($lastPurchase->category_id == $categoryId) {
                    $unit_cost_price = (float)$lastPurchase->unit_cost_price;
                    $paid_unit_cost_price = (float)$lastPurchase->paid_unit_cost_price;
                    $unit_sale_price = (float)$lastPurchase->unit_sale_price;
                } else {
                    $basePriceC = (float)($lastPurchase->base_unit_purchase_price ?? 0);
                    $basePriceS = (float)($lastPurchase->base_unit_sale_price ?? 0);
                    
                    $unit_cost_price = $productCtrl->calculateCategoryPrice($productId, $categoryId, $lastPurchase->base_category_id, $basePriceC);
                    $unit_sale_price = $productCtrl->calculateCategoryPrice($productId, $categoryId, $lastPurchase->base_category_id, $basePriceS);

                    $basePricePaidC = 0;
                    if ($lastPurchase->base_quantity > 0) {
                        $basePricePaidC = ($lastPurchase->paid_unit_cost_price * $lastPurchase->quantity) / $lastPurchase->base_quantity;
                    }
                    $paid_unit_cost_price = $productCtrl->calculateCategoryPrice($productId, $categoryId, $lastPurchase->base_category_id, $basePricePaidC);
                }
            } else {
                // First time
                $unit_cost_price = $staticCost;
                $paid_unit_cost_price = $staticCost;
                $unit_sale_price = $staticSale;
            }
        } else {
            // "static-price" or any other fallback
            $unit_cost_price = $staticCost;
            $paid_unit_cost_price = $staticCost;
            $unit_sale_price = $staticSale;
        }

        return [
            'unit_cost_price' => round($unit_cost_price, 2),
            'paid_unit_cost_price' => round($paid_unit_cost_price, 2),
            'unit_sale_price' => round($unit_sale_price, 2),
        ];
    }
    /** Purchases list (Inertia, Roadmap 5.5b): one row per batch, with what is left of it. */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $sort = in_array($request->input('sort'), ['created_at', 'expiry_date', 'total'], true) ? $request->input('sort') : 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $status = in_array($request->input('status'), ['month', 'expiring', 'expired', 'archived'], true) ? $request->input('status') : null;
        $today = today()->toDateString();

        $page = $this->listQuery($status === 'archived' ? Purchase::onlyTrashed() : Purchase::query())
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('purchases.batch_no', 'like', "%{$q}%")->orWhere('purchases.invoice_no', 'like', "%{$q}%")
                ->orWhereHas('product', fn ($p) => $p->where('product_name', 'like', "%{$q}%")->orWhere('barcode', $q))
                ->orWhereHas('supplier', fn ($p) => $p->where('name', 'like', "%{$q}%"))))
            ->when($status === 'month', fn ($w) => $w->where('purchases.created_at', '>=', now()->startOfMonth()))
            ->when($status === 'expiring', fn ($w) => $w->whereDate('purchases.expiry_date', '>', $today)->whereDate('purchases.expiry_date', '<=', today()->addDays(90))->having('remaining', '>', 0))
            ->when($status === 'expired', fn ($w) => $w->whereDate('purchases.expiry_date', '<=', $today)->having('remaining', '>', 0))
            ->orderBy($sort === 'total' ? 'purchases.paid_extra_total_cost_price' : "purchases.{$sort}", $dir)->orderByDesc('purchases.id')
            ->paginate($perPage)->withQueryString();

        $page->setCollection($page->getCollection()->map(fn (Purchase $p) => $this->row($p)));

        return \Inertia\Inertia::render('Purchases/Index', [
            'rows' => $page,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'stats' => fn () => $this->stats(),
            'currency' => currency_symbol(),
            'canManage' => $request->user()->can('stock.purchases.manage'),
        ]);
    }

    /** One purchase (batch): what was bought, what is left, what it cost, which invoices sold from it. */
    public function show(Request $request, $purchase)
    {
        $p = $this->listQuery(Purchase::withTrashed())->with(['taxes.tax' => fn ($t) => $t->withTrashed(), 'Saletaxes.tax' => fn ($t) => $t->withTrashed()])->whereKey($purchase)->firstOrFail();
        $row = $this->row($p);
        $sales = \App\Models\InvoiceItemLot::query()->where('purchase_id', $p->id)
            ->join('invoice_items', 'invoice_items.id', '=', 'invoice_item_lots.invoice_item_id')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->select('invoices.id as invoice_id', 'invoices.invoice_no', 'invoices.created_at', 'invoice_item_lots.base_qty', 'invoice_item_lots.returned_base_qty', 'invoice_item_lots.base_unit_sale_price')
            ->orderByDesc('invoice_item_lots.id')->paginate(10, ['*'], 'sales_page')->withQueryString();
        $sales->setCollection($sales->getCollection()->map(fn ($s) => [
            'invoice_id' => $s->invoice_id, 'invoice_no' => $s->invoice_no, 'date' => Carbon::parse($s->created_at)->format('d M Y'),
            'qty' => (float) $s->base_qty, 'returned' => (float) $s->returned_base_qty, 'unit_price' => (float) $s->base_unit_sale_price,
        ]));
        $taxRows = fn ($list) => $list->map(fn ($t) => ['name' => $t->tax?->name ?? 'Tax', 'rate' => (float) $t->tax_rate, 'unit' => (float) $t->tax_unit_amount, 'total' => (float) $t->tax_amount])->values();

        return \Inertia\Inertia::render('Purchases/Show', [
            'purchase' => $row + [
                'manufacturing_date' => $p->manufacturing_date ? Carbon::parse($p->manufacturing_date)->format('d M Y') : null,
                'unit_cost' => (float) $p->unit_cost_price, 'paid_unit_cost' => (float) $p->paid_unit_cost_price, 'extra_percent' => round((float) $p->extra_paid_percent, 2),
                'unit_sale' => (float) $p->unit_sale_price, 'total_cost' => (float) $p->total_cost_price, 'total_sale' => (float) $p->total_sale_price,
                'purchase_tax_total' => (float) $p->total_cost_tax_amount, 'sale_tax_total' => (float) $p->total_sale_tax_amount,
                'margin_percent' => (float) $p->paid_unit_cost_price > 0 ? round(((float) $p->unit_sale_price - (float) $p->paid_unit_cost_price) / (float) $p->paid_unit_cost_price * 100, 1) : null,
                'purchase_taxes' => $taxRows($p->taxes), 'sale_taxes' => $taxRows($p->Saletaxes),
                'supplier_id' => $p->supplier_id, 'product_id' => $p->product_id,
            ],
            'sales' => $sales,
            'currency' => currency_symbol(),
            'archiveBlocker' => $p->trashed() ? null : $this->purchases->archiveBlocker($p),
            'canManage' => $request->user()->can('stock.purchases.manage'),
        ]);
    }

    private function listQuery($query)
    {
        $lot = fn () => BaseStockSalePrice::withTrashed()->whereColumn('base_stock_sale_price.purchase_id', 'purchases.id');

        return $query->select('purchases.*')
            ->selectSub($lot()->selectRaw('coalesce(sum(remaining_base_stock), 0)'), 'remaining')
            ->with(['product' => fn ($p) => $p->withTrashed()->select('id', 'product_name', 'strength_id'), 'category' => fn ($c) => $c->withTrashed()->select('id', 'name'),
                'supplier:id,name', 'baseCategory:id,name']);
    }

    private function row(Purchase $p): array
    {
        $expiry = $p->expiry_date ? Carbon::parse($p->expiry_date) : null;
        $base = (float) $p->base_quantity;
        $remaining = (float) $p->remaining;

        return [
            'id' => $p->id,
            'date' => $p->created_at?->format('d M Y'),
            'product' => $p->product?->product_name ?? 'Unknown product',
            'unit' => $p->category?->name,
            'base_unit' => $p->baseCategory?->name,
            'quantity' => (float) $p->quantity,
            'base_quantity' => $base,
            'remaining' => $p->trashed() ? 0.0 : $remaining,
            'sold' => max(0.0, round($base - $remaining, 4)),
            'supplier' => $p->supplier?->name,
            'invoice_no' => $p->invoice_no,
            'batch' => $p->batch_no,
            'expiry' => $expiry?->format('d M Y'),
            'expiry_days' => $expiry ? (int) today()->diffInDays($expiry, false) : null,
            'total' => round((float) ($p->paid_extra_total_cost_price ?: $p->total_cost_price), 2),
            'archived' => $p->trashed(),
        ];
    }

    private function stats(): array
    {
        $today = today()->toDateString();
        $withStock = fn () => $this->listQuery(Purchase::query())->having('remaining', '>', 0);

        return [
            'total' => Purchase::count(),
            'month' => Purchase::where('created_at', '>=', now()->startOfMonth())->count(),
            'month_spend' => round((float) Purchase::where('created_at', '>=', now()->startOfMonth())->sum('paid_extra_total_cost_price'), 2),
            'expiring' => $withStock()->whereDate('purchases.expiry_date', '>', $today)->whereDate('purchases.expiry_date', '<=', today()->addDays(90))->toBase()->getCountForPagination(),
            'expired' => $withStock()->whereDate('purchases.expiry_date', '<=', $today)->toBase()->getCountForPagination(),
            'archived' => Purchase::onlyTrashed()->count(),
        ];
    }

    /** The one purchase form (Roadmap 5.5c): a supplier bill with one or more lines. ?product_id= preselects a product. */
    public function create(Request $request)
    {
        $preset = $request->integer('product_id') ? Product::query()->real()->find($request->integer('product_id')) : null;

        return \Inertia\Inertia::render('Purchases/Form', [
            'purchase' => null,
            'preset' => $preset ? ['value' => $preset->id, 'label' => $preset->product_name] : null,
            'currency' => currency_symbol(),
        ]);
    }

    /** Old "Add multiple purchase" screen: the one form takes several lines. */
    public function createMultiple(Request $request)
    {
        return redirect()->route('purchases.create', array_filter(['product_id' => $request->integer('product_id') ?: null]));
    }

    public function storeMultiple(Request $request)
    {
        // Legacy bulk form: purchases[] with one supplier / invoice no for the whole bill.
        $lines = collect($request->input('purchases', []))->map(fn ($p) => $this->legacyLine(is_array($p) ? $p : []))->values()->all();
        $created = $this->purchases->create(['supplier' => $this->legacySupplier($request->input('supplier')), 'invoice_no' => $request->input('invoice_no'), 'lines' => $lines]);

        return $this->stored($request, $created->count());
    }

    /**
     * Store a purchase: the new form posts {supplier: {id}|{name}, invoice_no, lines: [...]}; the legacy single form
     * posts flat fields, translated into one line. Every amount is computed by PurchaseService, never taken from here.
     */
    public function store(Request $request)
    {
        $input = $request->has('lines')
            ? $request->only(['supplier', 'invoice_no', 'lines'])
            : ['supplier' => $this->legacySupplier($request->input('supplier')), 'invoice_no' => $request->input('invoice_no'), 'lines' => [$this->legacyLine($request->all())]];
        $created = $this->purchases->create($input);
        $this->legacyImage($request, $created->first());

        return $this->stored($request, $created->count());
    }

    public function __construct(private readonly \App\Services\Stock\PurchaseService $purchases)
    {
    }

    /** [baseCategoryId, baseQty] for a quantity in one of the product's units (kept for POSController). */
    public function calculateBaseStock($productId, $selectedCategoryId, $quantity)
    {
        return app(\App\Services\Sales\PackagingService::class)->toBase((int) $productId, $selectedCategoryId === null || $selectedCategoryId === "" ? null : (int) $selectedCategoryId, (float) $quantity);
    }

    private function stored(Request $request, int $count)
    {
        $message = $count === 1 ? 'Purchase saved: the stock is ready to sell.' : "{$count} purchases saved: the stock is ready to sell.";
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return redirect()->route('purchases.index')->with('success', $message);
    }

    /** Legacy Blade fields → a PurchaseService line (client-side tax amounts are ignored on purpose). */
    private function legacyLine(array $f): array
    {
        $cost = $f['unit_cost_price'] ?? null;
        $paid = isset($f['paid_unit_cost_price']) && $f['paid_unit_cost_price'] !== '' ? $f['paid_unit_cost_price']
            : (is_numeric($cost) ? (float) $cost + (float) ($f['extra_paid_per_unit'] ?? 0) : null);

        return [
            'product_id' => $f['product'] ?? null, 'category_id' => $f['category'] ?? null, 'quantity' => $f['quantity'] ?? null,
            'unit_cost_price' => $cost, 'paid_unit_cost_price' => $paid, 'unit_sale_price' => $f['unit_sale_price'] ?? null,
            'batch_no' => $f['batch_no'] ?? null, 'manufacturing_date' => $f['manufacturing_date'] ?? null, 'expiry_date' => $f['expiry_date'] ?? null,
            'purchase_tax_ids' => collect($f['taxes'] ?? [])->pluck('id')->filter()->values()->all(),
            'sale_tax_ids' => collect($f['sale_taxes'] ?? [])->pluck('id')->filter()->values()->all(),
        ];
    }

    private function legacySupplier($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? ['id' => (int) $value] : ['name' => (string) $value];
    }

    private function legacyImage(Request $request, ?Purchase $purchase): void
    {
        if ($purchase && $request->hasFile('image') && $request->file('image')->isValid()) {
            $request->validate(['image' => 'image|mimes:jpg,jpeg,png,gif|max:4096']);
            $name = $purchase->id.'-'.time().'.'.$request->file('image')->extension();
            $request->file('image')->move(public_path('storage/purchases'), $name);
            $purchase->update(['image' => $name]);
        }
    }

    public function edit(Purchase $purchase)
    {
        $purchase->load(['product' => fn ($p) => $p->withTrashed(), 'supplier', 'taxes.tax' => fn ($t) => $t->withTrashed(), 'Saletaxes.tax' => fn ($t) => $t->withTrashed()]);
        $sold = $this->purchases->soldBase($purchase);
        $packaging = app(\App\Services\Sales\PackagingService::class);
        $soldInUnit = $sold > 0 ? round($packaging->fromBase((int) $purchase->product_id, (int) $purchase->category_id, $sold), 4) : 0.0;
        $taxes = fn ($list) => $list->filter(fn ($t) => $t->tax)->map(fn ($t) => ['value' => (int) $t->tax_id, 'label' => $t->tax->name, 'meta' => ['rate' => (float) $t->tax->rate]])->values();

        return \Inertia\Inertia::render('Purchases/Form', [
            'purchase' => [
                'id' => $purchase->id,
                'supplier' => $purchase->supplier ? ['value' => $purchase->supplier->id, 'label' => $purchase->supplier->name] : null,
                'invoice_no' => $purchase->invoice_no,
                'line' => [
                    'product' => ['value' => $purchase->product_id, 'label' => $purchase->product?->product_name ?? 'Unknown product'],
                    'category_id' => (int) $purchase->category_id,
                    'quantity' => (float) $purchase->quantity,
                    'unit_cost_price' => (float) $purchase->unit_cost_price,
                    'paid_unit_cost_price' => (float) $purchase->paid_unit_cost_price,
                    'unit_sale_price' => (float) $purchase->unit_sale_price,
                    'batch_no' => $purchase->batch_no,
                    'manufacturing_date' => $purchase->manufacturing_date ? Carbon::parse($purchase->manufacturing_date)->toDateString() : null,
                    'expiry_date' => $purchase->expiry_date ? Carbon::parse($purchase->expiry_date)->toDateString() : null,
                    'purchase_taxes' => $taxes($purchase->taxes),
                    'sale_taxes' => $taxes($purchase->Saletaxes),
                ],
                'sold' => $soldInUnit,
                'locked' => $sold > 0,
            ],
            'preset' => null,
            'currency' => currency_symbol(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \app\Models\Purchase $purchase
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Purchase $purchase)
    {
        $input = $request->has('line')
            ? $request->only(['supplier', 'invoice_no', 'line'])
            : ['supplier' => $this->legacySupplier($request->input('supplier')), 'invoice_no' => $request->input('invoice_no'), 'line' => $this->legacyLine($request->all())];
        $this->purchases->update($purchase, $input);
        $this->legacyImage($request, $purchase);

        return redirect()->route('purchases.show', $purchase->id)->with('success', 'Purchase updated.');
    }

    public function reports(Request $request)
    {
        $title = 'purchase reports';
        $from_date = Carbon::now()->startOfMonth()->toDateString();
        $to_date = Carbon::now()->endOfMonth()->toDateString();
        $purchases = Purchase::whereBetween(DB::raw('DATE(created_at)'), array($from_date, $to_date))->latest('created_at')->get();
        return view('admin.purchases.reports', compact('title', 'purchases', 'from_date', 'to_date'));
    }

    public function generateReport(Request $request)
    {
        $this->validate($request, [
            'from_date' => 'required',
            'to_date' => 'required'
        ]);
        $title = 'purchases reports';
        $purchases = Purchase::whereBetween(DB::raw('DATE(created_at)'), array($request->from_date, $request->to_date))->latest('created_at')->get();
        $from_date = $request->from_date;
        $to_date = $request->to_date;
        return view('admin.purchases.reports', compact(
            'purchases',
            'title',
            'from_date',
            'to_date'
        ));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $purchase = null)
    {
        $purchase = Purchase::query()->findOrFail($purchase ?? $request->input('id'));
        if ($reason = $this->purchases->archiveBlocker($purchase)) {
            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['success' => false, 'message' => $reason], 422)
                : back()->with('error', $reason);
        }
        $this->purchases->archive($purchase);
        $message = 'Purchase archived and its stock removed.';

        return $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['success' => true, 'message' => $message])
            : redirect()->route('purchases.index')->with('success', $message);
    }
}
