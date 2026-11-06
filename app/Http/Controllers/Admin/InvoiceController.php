<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\BaseStockSalePrice;
use App\Models\InvoiceItemReturn;
use App\Models\ProductParameter;
use App\Models\InvoiceItem;
use App\Models\ProductPreference;
use App\Models\Preference;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    private $productController;

    public function __construct(ProductController $productController)
    {
        $this->productController = $productController;
    }

    // ==============================
    // 1️⃣ List invoices
    // ==============================
    /** Invoices list (Inertia, Roadmap 5.8b). */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $sort = in_array($request->input('sort'), ['created_at', 'grand_total'], true) ? $request->input('sort') : 'created_at';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $status = in_array($request->input('status'), ['today', 'week', 'returns', 'returned'], true) ? $request->input('status') : null;
        $from = $request->date('from');
        $to = $request->date('to');

        $page = Invoice::query()->withCount('items')
            ->withExists(['items as has_returns' => fn ($w) => $w->whereHas('returns')])
            ->when($q !== '', fn ($w) => $w->where('invoice_no', 'like', "%{$q}%"))
            ->when($status === 'today', fn ($w) => $w->whereDate('created_at', today()))
            ->when($status === 'week', fn ($w) => $w->where('created_at', '>=', now()->subDays(7)))
            ->when($status === 'returns', fn ($w) => $w->whereHas('items.returns'))
            ->when($status === 'returned', fn ($w) => $w->where('fully_returned', true))
            ->when($from, fn ($w) => $w->whereDate('created_at', '>=', $from))
            ->when($to, fn ($w) => $w->whereDate('created_at', '<=', $to))
            ->orderBy($sort, $dir)->orderByDesc('id')
            ->paginate($perPage)->withQueryString();
        $page->setCollection($page->getCollection()->map(fn (Invoice $i) => [
            'id' => $i->id, 'invoice_no' => $i->invoice_no, 'date' => $i->created_at?->format('d M Y, g:i a'),
            'items' => (int) $i->items_count, 'grand_total' => (float) $i->grand_total, 'cash' => (float) $i->cash_received,
            'status' => $i->fully_returned ? 'returned' : ($i->has_returns ? 'partial' : 'sold'),
        ]));

        return \Inertia\Inertia::render('Invoices/Index', [
            'rows' => $page,
            'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status, 'from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'stats' => fn () => [
                'total' => Invoice::count(),
                'today' => Invoice::whereDate('created_at', today())->count(),
                'today_sales' => round((float) Invoice::whereDate('created_at', today())->sum('grand_total'), 2),
                'week' => Invoice::where('created_at', '>=', now()->subDays(7))->count(),
                'returns' => Invoice::whereHas('items.returns')->count(),
                'returned' => Invoice::where('fully_returned', true)->count(),
            ],
            'currency' => currency_symbol(),
        ]);
    }

    /** One invoice (Inertia): lines with what was returned and what is left, taxes, returns, history. */
    /**
     * Invoice numbers are unique per business only. A super admin outside a business sees every business (no scope), so
     * a number could match another business's invoice: they open the business first (security review 13e).
     */
    private function invoiceQuery(string $invoiceNo)
    {
        abort_if(auth()->user()?->hasRole('super-admin') && ! session()->has('impersonate_business_id'), 409,
            'Open the business first (Platform → Businesses → Open): invoice numbers are per business.');

        return Invoice::where('invoice_no', $invoiceNo);
    }

    public function show(Request $request, $invoice_no)
    {
        $invoice = $this->invoiceQuery($invoice_no)
            ->with(['items.category' => fn ($c) => $c->withTrashed(), 'items.product' => fn ($p) => $p->withTrashed()->with('type'), 'items.returns', 'items.lots.lot.purchase', 'histories.user:id,name'])
            ->firstOrFail();
        $returns = app(\App\Services\Sales\ReturnService::class);
        $showIncluded = ! $request->user()->isSalesPersonIn((int) $invoice->business_id);

        return \Inertia\Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id, 'invoice_no' => $invoice->invoice_no, 'date' => $invoice->created_at?->format('d M Y, g:i a'),
                'subtotal' => (float) $invoice->subtotal, 'line_discounts' => (float) $invoice->discount, 'net' => (float) $invoice->total,
                'invoice_discount' => (float) $invoice->invoice_discount_amount, 'tax' => (float) $invoice->tax_amount,
                'included_tax' => $showIncluded ? (float) $invoice->included_tax_amount : null,
                'grand_total' => (float) $invoice->grand_total, 'cash' => (float) $invoice->cash_received, 'change' => (float) $invoice->change_return,
                'fully_returned' => (bool) $invoice->fully_returned,
            ],
            'lines' => $invoice->items->map(fn (InvoiceItem $i) => [
                'id' => $i->id, 'name' => $i->name, 'type' => $i->product?->type?->name, 'unit' => $i->category?->name,
                'qty' => (float) $i->qty, 'returned' => round((float) $i->returns->sum('qty_returned'), 4), 'left' => $returns->left($i),
                'price' => (float) $i->price, 'system_price' => $i->server_unit_price !== null ? (float) $i->server_unit_price : null, 'overridden' => (bool) $i->price_overridden,
                // I-1: row_total is kept net of returns by the return logic; it is the line's current net amount.
                'discount' => (float) $i->discount_amount, 'row_total' => round((float) $i->row_total, 2), 'lot_mode' => $i->lot_selection_mode,
                'lots' => $i->lots->map(fn ($l) => ['batch' => $l->lot?->purchase?->batch_no, 'expiry' => $l->lot?->expiry_date ? \Illuminate\Support\Carbon::parse($l->lot->expiry_date)->toDateString() : null,
                    'qty' => (float) $l->base_qty])->values(),
            ])->values(),
            'taxes' => collect($invoice->consolidatedTaxBreakdown($showIncluded))->map(fn ($t) => [
                'name' => $t['name'], 'rate' => (float) ($t['rate'] ?? 0), 'amount' => round((float) $t['amount'], 2),
                'kind' => $t['tax_id'] === null ? 'gst' : (! empty($t['is_included']) ? 'included' : 'additive'),
            ])->values(),
            'returns' => InvoiceItemReturn::query()->where('invoice_id', $invoice->id)->selectRaw('return_no, min(created_at) as at, sum(refund_amount) as refund, sum(qty_returned) as qty')
                ->groupBy('return_no')->orderBy('at')->get()->map(fn ($r) => ['return_no' => $r->return_no, 'date' => Carbon::parse($r->at)->format('d M Y, g:i a'), 'refund' => round((float) $r->refund, 2)]),
            'history' => $invoice->histories->sortByDesc('id')->map(fn ($h) => ['action' => $h->action, 'description' => $h->description, 'by' => $h->user?->name, 'at' => $h->created_at?->format('d M Y, g:i a')])->values(),
            'currency' => currency_symbol(),
            'canReturn' => $request->user()->can('pos.returns.manage'),
        ]);
    }

    /** Return part of one line (Roadmap 5.8a: rules and amounts in ReturnService). */
    public function returnProduct(Request $request, $invoice_no, $item_id)
    {
        $data = $request->validate(['return_qty' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:255']],
            ['return_qty.gt' => 'Enter a quantity above 0.']);
        $invoice = $this->invoiceQuery($invoice_no)->firstOrFail();

        return $this->doReturn($request, $invoice, [(int) $item_id => (float) $data['return_qty']], $data['reason'] ?? null);
    }

    /** Return several lines (qty per item) or, without `qty`, everything left on the invoice. */
    public function returnInvoice(Request $request, $invoice_no)
    {
        $data = $request->validate(['qty' => ['nullable', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0'], 'reason' => ['nullable', 'string', 'max:255']]);
        $invoice = $this->invoiceQuery($invoice_no)->firstOrFail();
        $qtys = isset($data['qty']) ? array_filter(array_map('floatval', $data['qty']), fn ($q) => $q > 0) : null;

        return $this->doReturn($request, $invoice, $qtys, $data['reason'] ?? null);
    }

    /** Refund preview for the return dialog (read-only). */
    public function returnPreview(Request $request, $invoice_no)
    {
        $data = $request->validate(['qty' => ['required', 'array'], 'qty.*' => ['nullable', 'numeric', 'min:0']]);
        $invoice = $this->invoiceQuery($invoice_no)->firstOrFail();
        $preview = app(\App\Services\Sales\ReturnService::class)->preview($invoice, $data['qty']);
        $preview['lines'] = array_map(fn ($l) => array_diff_key($l, ['taxes' => 1]), $preview['lines']);

        return response()->json($preview);
    }

    private function doReturn(Request $request, Invoice $invoice, ?array $qtys, ?string $reason)
    {
        try {
            $result = app(\App\Services\Sales\ReturnService::class)->return($invoice, $qtys, $reason, auth()->id());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e); // I-2: details stay in the log
            $message = $e instanceof \App\Exceptions\FbrNotConfiguredException || str_contains(get_class($e), 'Fbr')
                ? 'The FBR credit note could not be created, so nothing was returned. Try again or check the FBR settings.'
                : 'The return could not be saved. Nothing was changed; please try again.';

            return $request->expectsJson() && ! $request->header('X-Inertia')
                ? response()->json(['message' => $message], 500)
                : back()->with('error', $message);
        }

        $message = "Return {$result['return_no']} saved. Refund the customer ".currency_symbol().' '.number_format($result['refund'], 2).'.';
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['success' => true, 'message' => $message] + $result);
        }

        return back()->with('success', $message)->with('return', $result);
    }

    // processItemReturn / generateReturnNo / recalculateInvoiceTotals / restoreStockBySalePreference moved to
    // App\Services\Sales\ReturnService; printReturnReceipt (receipt from browser data, I-5) removed: returns print from
    // their stored snapshot (ReturnController::printReturn).

    public function printInvoice($invoice_no)
    {
        $invoice = $this->invoiceQuery($invoice_no)->with(['items.taxes', 'business'])->firstOrFail();

        $cartItems = [];
        $subtotal = 0;
        $grossSubtotal = 0;
        $totalItemDiscount = 0;
        
        foreach ($invoice->items as $item) {
            $productModel = \App\Models\Product::find($item->product_id);
            $categoryModel = \App\Models\Category::find($item->category_id);
            
            $cartItems[] = [
                'name' => $item->name,
                'price' => $item->price,
                'qty' => $item->qty,
                'discount_selected_type' => $item->discount_type,
                'discount_percent' => $item->discount_type === 'percent' ? $item->discount_value : 0,
                'discount_amount' => $item->discount_type === 'amount' ? $item->discount_value : 0,
                'total' => $item->row_total,
                'category_name' => $categoryModel ? $categoryModel->name : '',
                'strength' => $productModel && $productModel->strength ? $productModel->strength->name : '',
                'product_type' => $productModel && $productModel->type ? $productModel->type->name : ''
            ];
            
            $subtotal += $item->row_total;
            $grossSubtotal += ($item->price * $item->qty);
            $totalItemDiscount += $item->discount_amount;
        }

        return view('admin.pos.receipt-print', [
            'cartItems' => $cartItems,
            'subtotal' => $subtotal,
            'grossSubtotal' => $grossSubtotal,
            'totalItemDiscount' => $totalItemDiscount,
            'invoiceDiscountValue' => $invoice->invoice_discount_value,
            'invoiceDiscountType' => $invoice->invoice_discount_type,
            'invoiceDiscount' => $invoice->invoice_discount_amount,
            'grandTotal' => $invoice->grand_total,
            'cashReceived' => $invoice->cash_received,
            'changeReturn' => $invoice->change_return,
            'invoice_no' => $invoice->invoice_no,
            'business' => $invoice->business,
            'taxBreakdown' => $invoice->consolidatedTaxBreakdown(),
        ]);
    }
}
