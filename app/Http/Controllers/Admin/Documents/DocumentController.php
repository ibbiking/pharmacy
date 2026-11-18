<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Models\Documents\Document;
use App\Models\Documents\PaymentAllocation;
use App\Services\Documents\DocumentService;
use App\Services\Stock\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * B2B documents (Roadmap 8–9): one controller for every type; routes `selling.<slug>.*` (sales) and `buying.<slug>.*`
 * (purchasing) pick the type, shared routes `selling.docs.*` / `buying.docs.*` act on one document.
 * Pages: Documents/Index.tsx (list), Documents/Form.tsx (draft), Documents/Show.tsx (detail + actions), Documents/Receive.tsx.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $documents)
    {
    }

    /** Type from the route name: selling.invoices.index → sales_invoice. */
    private function type(Request $request): string
    {
        $name = (string) $request->route()?->getName();
        [$side, $slug] = array_pad(explode('.', $name), 3, null);
        foreach (Document::TYPES as $type => $meta) {
            if ($meta['slug'] === $slug && ($meta['side'] === 'sales') === ($side === 'selling')) {
                return $type;
            }
        }
        abort(404);
    }

    private function side(Document|string $d): string
    {
        $type = $d instanceof Document ? $d->type : $d;

        return Document::TYPES[$type]['side'] === 'sales' ? 'selling' : 'buying';
    }

    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    private function canManage(Request $request, string $type): bool
    {
        return $request->user()->can(Document::TYPES[$type]['side'] === 'sales' ? 'b2b_sales.manage' : 'b2b_purchase.manage');
    }

    public function index(Request $request)
    {
        $type = $this->type($request);
        $meta = Document::TYPES[$type];
        $q = trim((string) $request->input('q', ''));
        $perPage = in_array($request->integer('per_page'), [10, 20, 50], true) ? $request->integer('per_page') : 20;
        $sort = in_array($request->input('sort'), ['date', 'number', 'total', 'balance', 'due_date'], true) ? $request->input('sort') : 'date';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $statuses = $this->statuses($type);
        $status = in_array($request->input('status'), array_merge(array_keys($statuses), ['overdue']), true) ? $request->input('status') : null;
        $sales = $meta['side'] === 'sales';

        $base = fn () => Document::query()->where('type', $type);
        $rows = $base()->with([$sales ? 'customer:id,name,company' : 'supplier:id,name,company'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%")
                ->orWhereHas($sales ? 'customer' : 'supplier', fn ($c) => $c->where('name', 'like', "%{$q}%"))))
            ->when($status === 'overdue', fn ($w) => $w->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today()))
            ->when($status && $status !== 'overdue', fn ($w) => $w->where('status', $status))
            ->orderBy($sort, $dir)->orderBy('id', $dir)
            ->paginate($perPage)->withQueryString()
            ->through(fn (Document $d) => [
                'id' => $d->id, 'number' => $d->number, 'party' => $d->party()?->name, 'party_sub' => $d->party()?->company, 'date' => $d->date?->format('d M Y'),
                'due_date' => $d->due_date?->format('d M Y'), 'status' => $d->isOverdue() ? 'overdue' : $d->status, 'total' => $d->total, 'balance' => $d->balance,
                'reference' => $d->reference,
            ]);

        $stats = function () use ($base, $statuses, $type) {
            $out = ['all' => $base()->count()];
            foreach (array_keys($statuses) as $s) {
                $out[$s] = $base()->where('status', $s)->count();
            }
            if (in_array($type, ['sales_invoice', 'bill'], true)) {
                $out['overdue'] = $base()->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->count();
                $out['open_amount'] = round((float) $base()->whereIn('status', ['unpaid', 'partial'])->sum('balance'), 2);
                $out['month_total'] = round((float) $base()->whereNotIn('status', ['draft', 'void'])->where('date', '>=', now()->startOfMonth())->sum('total'), 2);
            }

            return $out;
        };

        return Inertia::render('Documents/Index', [
            'type' => $type, 'meta' => $meta + ['side_route' => $this->side($type)],
            'rows' => $rows, 'filters' => ['q' => $q, 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage, 'status' => $status],
            'statuses' => collect($statuses)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'stats' => $stats,
            'canCreate' => $this->canManage($request, $type) && ! in_array($type, ['credit_note', 'bill', 'vendor_credit', 'goods_receipt'], true),
            'currency' => currency_symbol(),
        ]);
    }

    private function statuses(string $type): array
    {
        return match ($type) {
            'quotation' => ['draft' => 'Drafts', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'converted' => 'Converted'],
            'sales_order' => ['draft' => 'Drafts', 'open' => 'Open', 'invoiced' => 'Invoiced', 'cancelled' => 'Cancelled'],
            'sales_invoice', 'bill' => ['draft' => 'Drafts', 'unpaid' => 'Unpaid', 'partial' => 'Partly paid', 'paid' => 'Paid', 'void' => 'Void'],
            'credit_note', 'vendor_credit' => ['draft' => 'Drafts', 'open_credit' => 'Credit open', 'applied' => 'Applied', 'void' => 'Void'],
            'purchase_order' => ['draft' => 'Drafts', 'sent' => 'Sent', 'partially_received' => 'Partly received', 'received' => 'Received', 'cancelled' => 'Cancelled'],
            'goods_receipt' => ['received' => 'Received'],
            default => [],
        };
    }

    public function create(Request $request)
    {
        $type = $this->type($request);
        abort_unless($this->canManage($request, $type), 403);
        abort_if(in_array($type, ['credit_note', 'bill', 'vendor_credit', 'goods_receipt'], true), 404);

        return $this->form($request, $type, null);
    }

    public function edit(Request $request, int $id)
    {
        $doc = Document::query()->with('lines.product:id,product_name', 'lines.tax:id,name,rate', 'customer', 'supplier', 'warehouse')->findOrFail($id);
        abort_unless($this->canManage($request, $doc->type), 403);
        abort_unless($doc->status === 'draft', 404);

        return $this->form($request, $doc->type, $doc);
    }

    private function form(Request $request, string $type, ?Document $doc)
    {
        $businessId = $this->businessId();
        $warehouses = app(WarehouseService::class);
        $party = null;
        if (! $doc && $request->integer('customer_id')) {
            $c = \App\Models\Customer::find($request->integer('customer_id'));
            $party = $c ? ['value' => $c->id, 'label' => $c->name] : null;
        }
        if (! $doc && $request->integer('supplier_id')) {
            $s = \App\Models\Supplier::find($request->integer('supplier_id'));
            $party = $s ? ['value' => $s->id, 'label' => $s->name] : null;
        }
        $defaultWarehouse = $warehouses->enabled($businessId) ? \App\Models\Inventory\Warehouse::find($doc?->warehouse_id ?? $warehouses->defaultId($businessId)) : null;

        return Inertia::render('Documents/Form', [
            'type' => $type, 'meta' => Document::TYPES[$type] + ['side_route' => $this->side($type)],
            'document' => $doc ? [
                'id' => $doc->id, 'number' => $doc->number, 'date' => $doc->date?->toDateString(), 'due_date' => $doc->due_date?->toDateString(),
                'reference' => $doc->reference, 'notes' => $doc->notes, 'terms' => $doc->terms, 'source_id' => $doc->source_id, 'source' => $doc->source?->number,
                'party' => ($p = $doc->party()) ? ['value' => $p->id, 'label' => $p->name] : null,
                'lines' => $doc->lines->map(fn ($l) => [
                    'source_line_id' => $l->source_line_id, 'product' => $l->product ? ['value' => $l->product_id, 'label' => $l->product->product_name] : null,
                    'category_id' => $l->category_id, 'quantity' => (string) (float) $l->qty, 'unit_price' => (string) (float) $l->unit_price,
                    'discount_percent' => $l->discount_percent > 0 ? (string) (float) $l->discount_percent : '', 'description' => $l->description ?? '',
                    'tax' => $l->tax ? ['value' => $l->tax->id, 'label' => $l->tax->name, 'meta' => ['rate' => (float) $l->tax->rate]] : null,
                ]),
            ] : null,
            'party' => $party,
            'warehouse' => $defaultWarehouse ? ['value' => $defaultWarehouse->id, 'label' => $defaultWarehouse->name] : null,
            'inventoryPro' => $warehouses->enabled($businessId),
            'canChangePrice' => Document::TYPES[$type]['side'] !== 'sales' || $request->user()->can('b2b_sales.price_change'),
            'today' => today()->toDateString(),
            'currency' => currency_symbol(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $this->type($request);
        abort_unless($this->canManage($request, $type), 403);
        $doc = $this->documents->save($type, $request->all(), null, $request->user());

        session()->flash('drawer_follow', true); // inside the side drawer: show the result there

        return redirect()->route($this->side($doc).'.docs.show', $doc->id)->with('success', "{$doc->typeLabel()} {$doc->number} saved as a draft.");
    }

    public function update(Request $request, int $id)
    {
        $doc = Document::query()->findOrFail($id);
        abort_unless($this->canManage($request, $doc->type), 403);
        $doc = $this->documents->save($doc->type, $request->all() + ['source_id' => $doc->source_id], $doc, $request->user());

        session()->flash('drawer_follow', true); // inside the side drawer: show the result there

        return redirect()->route($this->side($doc).'.docs.show', $doc->id)->with('success', "{$doc->typeLabel()} {$doc->number} updated.");
    }

    public function show(Request $request, int $id)
    {
        $doc = Document::query()->with(['lines.product:id,product_name', 'lines.unit:id,name', 'lines.tax:id,name', 'customer', 'supplier', 'warehouse:id,name', 'source', 'children', 'creator:id,name'])->findOrFail($id);
        abort_unless($request->user()->can($doc->isSales() ? 'b2b_sales.view' : 'b2b_purchase.view'), 403);
        $side = $this->side($doc);
        $allocations = PaymentAllocation::query()->where('document_id', $doc->id)->with('payment:id,number,date,method')->get()
            ->map(fn ($a) => ['amount' => $a->amount, 'payment' => $a->payment?->number, 'payment_id' => $a->payment_id, 'date' => $a->payment?->date?->format('d M Y') ?? $a->created_at?->format('d M Y'),
                'credit' => $a->credit_document_id ? Document::withTrashed()->find($a->credit_document_id)?->number : null, 'credit_id' => $a->credit_document_id]);

        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $doc->id, 'type' => $doc->type, 'label' => $doc->typeLabel(), 'number' => $doc->number, 'status' => $doc->isOverdue() ? 'overdue' : $doc->status,
                'raw_status' => $doc->status, 'date' => $doc->date?->format('d M Y'), 'due_date' => $doc->due_date?->format('d M Y'), 'reference' => $doc->reference,
                'party' => ($p = $doc->party()) ? ['id' => $p->id, 'name' => $p->name, 'company' => $p->company, 'email' => $p->email, 'phone' => $p->phone, 'address' => $p->address] : null,
                'warehouse' => $doc->warehouse?->name, 'notes' => $doc->notes, 'terms' => $doc->terms, 'by' => $doc->creator?->name,
                'subtotal' => $doc->subtotal, 'discount_total' => $doc->discount_total, 'tax_total' => $doc->tax_total, 'total' => $doc->total, 'paid' => $doc->paid,
                'balance' => $doc->balance, 'cost_total' => $doc->cost_total,
                // Linked (made by a POS sale / return / purchase): where it came from.
                'origin' => $doc->origin ? ['label' => ['pos' => 'POS sale', 'pos_return' => 'POS return', 'purchase' => 'Purchase'][$doc->origin] ?? $doc->origin, 'href' => $doc->href()] : null,
                'source' => $doc->source ? ['id' => $doc->source->id, 'number' => $doc->source->number, 'label' => $doc->source->typeLabel()] : null,
                'children' => $doc->children->map(fn ($c) => ['id' => $c->id, 'number' => $c->number, 'label' => $c->typeLabel(), 'status' => $c->status]),
                'lines' => $doc->lines->map(fn ($l) => [
                    'id' => $l->id, 'product' => $l->product?->product_name, 'description' => $l->description, 'unit' => $l->unit?->name, 'qty' => $l->qty, 'unit_price' => $l->unit_price,
                    'discount_percent' => $l->discount_percent, 'tax' => $l->tax?->name, 'tax_rate' => $l->tax_rate, 'tax_amount' => $l->tax_amount, 'net' => $l->net_amount,
                    'total' => $l->line_total, 'done_qty' => $l->done_qty,
                ]),
            ],
            'allocations' => $allocations,
            // Shipping (sales orders / invoices of a Wholesale business).
            'shipments' => in_array($doc->type, ['sales_order', 'sales_invoice'], true) && $doc->origin === null
                ? \App\Models\Documents\Shipment::query()->where('document_id', $doc->id)->orderBy('id')->get()->map(fn ($x) => ['id' => $x->id, 'number' => $x->number, 'carrier' => $x->carrier,
                    'tracking_no' => $x->tracking_no, 'packages' => $x->packages, 'weight_kg' => $x->weight_kg, 'status' => $x->status, 'notes' => $x->notes,
                    'shipped_at' => $x->shipped_at?->format('d M Y'), 'delivered_at' => $x->delivered_at?->format('d M Y')])->all()
                : null,
            'canShip' => $request->user()->can('b2b_sales.manage') && ! in_array($doc->status, ['draft', 'void', 'cancelled'], true),
            'actions' => $this->actions($request, $doc),
            'side' => $side,
            'currency' => currency_symbol(),
        ]);
    }

    /** Buttons the user may press on this document right now. */
    private function actions(Request $request, Document $doc): array
    {
        if (! $this->canManage($request, $doc->type)) {
            return [];
        }
        $a = [];
        $draft = $doc->status === 'draft';
        $add = function (string $key, string $label, string $icon, string $kind = 'light', ?string $confirm = null) use (&$a) {
            $a[] = compact('key', 'label', 'icon', 'kind', 'confirm');
        };
        // Linked documents (made by a POS sale / return / purchase) are changed through their source; only paying is here.
        if ($doc->origin !== null) {
            if (in_array($doc->status, ['unpaid', 'partial'], true) && $request->user()->can($doc->isSales() ? 'customer_credit.payments' : 'supplier_credit.payments')) {
                $add('pay', $doc->isSales() ? 'Record payment' : 'Pay bill', 'cash-coin', 'primary');
            }

            return $a;
        }
        if ($draft) {
            $add('edit', 'Edit', 'pencil');
        }
        match ($doc->type) {
            'quotation' => [
                $draft ? $add('send', 'Mark as sent', 'send') : null,
                in_array($doc->status, ['draft', 'sent'], true) ? $add('accept', 'Accepted', 'hand-thumbs-up') : null,
                in_array($doc->status, ['draft', 'sent'], true) ? $add('decline', 'Declined', 'hand-thumbs-down') : null,
                in_array($doc->status, ['draft', 'sent', 'accepted'], true) ? $add('convert:sales_order', 'Convert to sales order', 'clipboard-check') : null,
                in_array($doc->status, ['draft', 'sent', 'accepted'], true) ? $add('convert:sales_invoice', 'Convert to invoice', 'receipt-cutoff', 'primary') : null,
            ],
            'sales_order' => [
                $draft ? $add('confirm', 'Confirm order', 'check2-circle', 'primary') : null,
                $doc->status === 'open' ? $add('convert:sales_invoice', 'Create invoice', 'receipt-cutoff', 'primary') : null,
                in_array($doc->status, ['draft', 'open'], true) ? $add('cancel', 'Cancel order', 'x-circle', 'outline-danger', 'Cancel this order?') : null,
            ],
            'sales_invoice' => [
                $draft ? $add('issue', 'Issue invoice (takes stock)', 'send-check', 'primary', 'Issue this invoice? Stock is taken from the batches and it becomes payable.') : null,
                in_array($doc->status, ['unpaid', 'partial'], true) && $request->user()->can('customer_credit.payments') ? $add('pay', 'Record payment', 'cash-coin', 'primary') : null,
                in_array($doc->status, ['unpaid', 'partial', 'paid'], true) ? $add('convert:credit_note', 'Credit note', 'arrow-return-left') : null,
                $doc->status === 'unpaid' ? $add('void', 'Void', 'slash-circle', 'outline-danger', 'Void this invoice? The stock goes back to its batches.') : null,
            ],
            'credit_note', 'vendor_credit' => [
                $draft ? $add('issue', $doc->type === 'credit_note' ? 'Issue credit note (restocks)' : 'Issue vendor credit (returns stock)', 'send-check', 'primary', 'Issue it? Stock moves and the amount is applied to the source document.') : null,
                $doc->status === 'open_credit' && ! PaymentAllocation::query()->where('document_id', $doc->id)->exists() && $doc->paid <= 0 ? $add('void', 'Void', 'slash-circle', 'outline-danger', 'Void it?') : null,
            ],
            'purchase_order' => [
                $draft ? $add('send', 'Mark as sent', 'send') : null,
                in_array($doc->status, ['draft', 'sent', 'partially_received'], true) ? $add('receive', 'Receive goods', 'box-arrow-in-down', 'primary') : null,
                in_array($doc->status, ['partially_received', 'received'], true) ? $add('convert:bill', 'Create bill', 'receipt', 'primary') : null,
                in_array($doc->status, ['draft', 'sent'], true) ? $add('cancel', 'Cancel order', 'x-circle', 'outline-danger', 'Cancel this purchase order?') : null,
            ],
            'bill' => [
                $draft ? $add('issue', 'Enter bill', 'send-check', 'primary', 'Enter this bill? It becomes payable to the supplier.') : null,
                in_array($doc->status, ['unpaid', 'partial'], true) && $request->user()->can('supplier_credit.payments') ? $add('pay', 'Pay bill', 'cash-coin', 'primary') : null,
                in_array($doc->status, ['unpaid', 'partial', 'paid'], true) ? $add('convert:vendor_credit', 'Vendor credit', 'arrow-return-right') : null,
                $doc->status === 'unpaid' ? $add('void', 'Void', 'slash-circle', 'outline-danger', 'Void this bill?') : null,
            ],
            default => null,
        };
        if ($draft) {
            $add('delete', 'Delete draft', 'trash', 'outline-danger', 'Delete this draft?');
        }

        return $a;
    }

    public function action(Request $request, int $id, string $action)
    {
        $doc = Document::query()->findOrFail($id);
        abort_unless($this->canManage($request, $doc->type), 403);
        $side = $this->side($doc);
        if (str_starts_with($action, 'convert:')) {
            $new = $this->documents->convert($doc, substr($action, 8), $request->user());
            session()->flash('drawer_follow', true);

            return redirect()->route("{$side}.docs.edit", $new->id)->with('success', "{$new->typeLabel()} {$new->number} created as a draft: check it and save.");
        }
        // Issuing an invoice / bill can record what was paid there and then: not paid, part paid or paid in full.
        $paidNow = $action === 'issue' && in_array($doc->type, ['sales_invoice', 'bill'], true) ? round((float) $request->input('paid_now', 0), 2) : 0.0;
        if ($paidNow > 0) {
            $request->validate(['paid_now' => ['numeric', 'gt:0', 'max:999999999'], 'method' => ['nullable', 'in:'.implode(',', array_keys(\App\Models\Documents\Payment::METHODS))]]);
            abort_unless($request->user()->can($doc->isSales() ? 'customer_credit.payments' : 'supplier_credit.payments'), 403);
        }
        $doc = \Illuminate\Support\Facades\DB::transaction(function () use ($doc, $action, $request, $paidNow) {
            $doc = $this->documents->action($doc, $action, $request->user());
            if ($paidNow > 0) {
                $this->documents->recordPayment($doc->isSales() ? 'in' : 'out', [
                    $doc->isSales() ? 'customer_id' : 'supplier_id' => $doc->isSales() ? $doc->customer_id : $doc->supplier_id,
                    'date' => today()->toDateString(), 'amount' => min($paidNow, (float) $doc->balance), 'method' => $request->input('method', 'cash'),
                    'reference' => $doc->number, 'allocations' => [['document_id' => $doc->id, 'amount' => min($paidNow, (float) $doc->balance)]],
                ], $request->user());
            }

            return $doc->fresh() ?? $doc;
        });
        if ($action === 'delete') {
            return redirect()->route($side.'.'.Document::TYPES[$doc->type]['slug'].'.index')->with('success', 'Draft deleted.');
        }
        $messages = ['send' => 'Marked as sent.', 'accept' => 'Marked as accepted.', 'decline' => 'Marked as declined.', 'confirm' => 'Order confirmed.',
            'cancel' => 'Cancelled.', 'issue' => "{$doc->typeLabel()} {$doc->number} issued.", 'void' => "{$doc->typeLabel()} {$doc->number} voided."];

        session()->flash('drawer_follow', true); // inside the side drawer: show the result there

        return redirect()->route("{$side}.docs.show", $doc->id)->with('success', $messages[$action] ?? 'Done.');
    }

    public function receiveForm(Request $request, int $id)
    {
        $po = Document::query()->where('type', 'purchase_order')->with('lines.product:id,product_name,has_expiry', 'lines.unit:id,name', 'supplier', 'warehouse')->findOrFail($id);
        abort_unless($this->canManage($request, 'purchase_order'), 403);
        $businessId = $this->businessId();
        $prices = app(\App\Services\Sales\SalePriceCalculator::class);
        $resolver = app(\App\Services\Sales\SalePreferenceResolver::class);

        return Inertia::render('Documents/Receive', [
            'po' => ['id' => $po->id, 'number' => $po->number, 'supplier' => $po->supplier?->name, 'warehouse' => $po->warehouse ? ['value' => $po->warehouse->id, 'label' => $po->warehouse->name] : null],
            'lines' => $po->lines->map(function ($l) use ($prices, $resolver) {
                try {
                    $sale = $prices->unitPrice((int) $l->product_id, (int) $l->category_id, $resolver->forProduct((int) $l->product_id));
                } catch (\Throwable) {
                    $sale = 0;
                }
                $unitCost = $l->qty > 0 ? round($l->net_amount / $l->qty, 2) : (float) $l->unit_price;

                return ['id' => $l->id, 'product' => $l->product?->product_name, 'unit' => $l->unit?->name, 'ordered' => $l->qty, 'received' => $l->done_qty,
                    'left' => round($l->qty - $l->done_qty, 4), 'unit_cost' => $unitCost, 'sale_price' => round(max((float) $sale, $unitCost * 1.2), 2),
                    'has_expiry' => (bool) $l->product?->has_expiry];
            })->filter(fn ($l) => $l['left'] > 0)->values(),
            'inventoryPro' => app(WarehouseService::class)->enabled($businessId),
            'expiryRequired' => \App\Models\Product::expiryDefault($businessId),
            'today' => today()->toDateString(),
            'currency' => currency_symbol(),
        ]);
    }

    public function receive(Request $request, int $id)
    {
        $po = Document::query()->where('type', 'purchase_order')->findOrFail($id);
        abort_unless($this->canManage($request, 'purchase_order'), 403);
        $grn = $this->documents->receive($po, $request->all(), $request->user());

        session()->flash('drawer_follow', true); // inside the side drawer: show the result there

        return redirect()->route('buying.docs.show', $po->id)->with('success', "Goods received ({$grn->number}): the stock is ready to sell.");
    }

    /** A4 (default) or thermal print of a document. */
    public function print(Request $request, int $id)
    {
        $doc = Document::query()->with(['lines.product:id,product_name', 'lines.unit:id,name', 'lines.tax:id,name', 'customer', 'supplier', 'business'])->findOrFail($id);
        abort_unless($request->user()->can($doc->isSales() ? 'b2b_sales.view' : 'b2b_purchase.view'), 403);
        $format = $request->input('format') === 'thermal' ? 'thermal' : 'a4';

        return view('documents.print', ['doc' => $doc, 'business' => $doc->business, 'format' => $format, 'currency' => currency_symbol()]);
    }
}
