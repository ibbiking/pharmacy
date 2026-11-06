<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InvoiceItemReturn;
use App\Models\Invoice;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class ReturnController extends Controller
{
    /** Returns report (Inertia, Roadmap 5.9a): one row per return, amounts from the stored snapshots (RP-5). */
    public function report(Request $request)
    {
        $preset = in_array($request->input('range'), ['today', '7d', '30d', 'month', 'last_month', 'custom'], true) ? $request->input('range') : 'month';
        [$from, $to] = \App\Services\Reports\SalesReportService::range($preset, $request->input('from'), $request->input('to'));
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $base = fn () => InvoiceItemReturn::query()->where('invoice_item_returns.business_id', $businessId)->whereBetween('invoice_item_returns.created_at', [$from, $to]);

        $rows = $base()->join('invoices', 'invoices.id', '=', 'invoice_item_returns.invoice_id')->leftJoin('users', 'users.id', '=', 'invoice_item_returns.handled_by')
            ->selectRaw('invoice_item_returns.return_no, max(invoices.invoice_no) as invoice_no, min(invoice_item_returns.created_at) as at, count(*) as line_count, sum(invoice_item_returns.qty_returned) as qty, sum(invoice_item_returns.refund_amount) as refund, max(users.name) as by_name, max(invoice_item_returns.reason) as reason')
            ->groupBy('invoice_item_returns.return_no')->orderByDesc('at')->paginate(20)->withQueryString();
        $rows->setCollection($rows->getCollection()->map(fn ($r) => [
            'return_no' => $r->return_no, 'invoice_no' => $r->invoice_no, 'date' => Carbon::parse($r->at)->format('d M Y, g:i a'),
            'lines' => (int) $r->line_count, 'qty' => (float) $r->qty, 'refund' => round((float) $r->refund, 2), 'by' => $r->by_name, 'reason' => $r->reason,
        ]));
        $k = $base()->selectRaw('count(distinct return_no) as n, coalesce(sum(refund_amount),0) as refund, coalesce(sum(qty_returned),0) as qty')->first();

        return \Inertia\Inertia::render('Reports/Returns', [
            'filters' => ['range' => $preset, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis' => ['returns' => (int) $k->n, 'refunds' => round((float) $k->refund, 2), 'qty' => (float) $k->qty],
            'rows' => $rows,
            'currency' => currency_symbol(),
        ]);
    }

    /** One return (Inertia, Roadmap 5.8b): everything from the stored snapshot. */
    public function show($return_no)
    {
        $data = $this->snapshot($return_no);

        return \Inertia\Inertia::render('Returns/Show', $data + ['currency' => currency_symbol()]);
    }

    /** Reprint a return exactly as it was (I-5, I-7): rows matched by invoice LINE, amounts from the snapshot. */
    public function printReturn($return_no)
    {
        $d = $this->snapshot($return_no);
        $clawback = array_sum(array_column($d['lines'], 'invoice_discount'));

        return view('admin.invoices.return-receipt-print', [
            'invoice_no' => $d['invoice']['invoice_no'],
            'invoice_date' => $d['invoice']['date'],
            'return_no' => $return_no,
            'returnedItems' => array_map(fn ($l) => [
                'name' => $l['name'], 'strength' => '', 'product_type' => $l['type'], 'category_name' => $l['unit'],
                'discount_selected_type' => 'amount', 'discount_percent' => 0, 'discount_amount' => $l['line_discount'],
                'qty' => $l['qty'], 'price' => $l['price'], 'gross_total' => $l['gross'], 'total' => $l['gross'] - $l['line_discount'],
            ], $d['lines']),
            'metadata' => ['global_discount_clawback' => $clawback, 'total_unit_discount' => array_sum(array_column($d['lines'], 'line_discount')),
                'gross_subtotal' => array_sum(array_column($d['lines'], 'gross'))],
            // The view prints "total − clawback" as the cash refund; the stored refund already excludes it.
            'totalReturn' => $d['refund'] + $clawback,
            'taxBreakdown' => array_values(array_filter($d['taxes'], fn ($t) => $t['kind'] !== 'included')),
            'business' => \App\Models\Business::find($d['invoice']['business_id']),
        ]);
    }

    private function snapshot(string $returnNo): array
    {
        $rows = InvoiceItemReturn::query()->where('return_no', $returnNo)
            ->with(['invoice', 'invoiceItem.category' => fn ($c) => $c->withTrashed(), 'invoiceItem.product' => fn ($p) => $p->withTrashed()->with('type'), 'handler:id,name'])->get();
        abort_if($rows->isEmpty(), 404, 'Return not found.');
        $invoice = $rows->first()->invoice;

        $taxes = [];
        $legacy = false;
        foreach ($rows as $r) {
            if ($r->taxes === null) {
                $legacy = true; // returned before snapshots existed: its tax refund was not recorded
            }
            foreach ($r->taxes ?? [] as $t) {
                $key = $t['kind'].':'.($t['tax_id'] ?? 'gst').':'.$t['name'];
                $taxes[$key] ??= ['tax_id' => $t['tax_id'], 'name' => $t['name'], 'rate' => $t['rate'], 'kind' => $t['kind'], 'amount' => 0.0];
                $taxes[$key]['amount'] += (float) $t['amount'];
            }
        }

        return [
            'return_no' => $returnNo,
            'invoice' => ['invoice_no' => $invoice?->invoice_no, 'id' => $invoice?->id, 'date' => $invoice?->created_at?->format('d-M-y g:ia'), 'business_id' => $invoice?->business_id],
            'date' => $rows->first()->created_at?->format('d M Y, g:i a'),
            'by' => $rows->first()->handler?->name,
            'reason' => $rows->first()->reason,
            'lines' => $rows->map(fn ($r) => [
                'name' => $r->invoiceItem?->name ?? $r->invoiceItem?->product?->product_name ?? 'Item',
                'type' => $r->invoiceItem?->product?->type?->name, 'unit' => $r->invoiceItem?->category?->name,
                'qty' => (float) $r->qty_returned, 'price' => (float) ($r->price ?? $r->invoiceItem?->price ?? 0),
                'gross' => round((float) ($r->price ?? $r->invoiceItem?->price ?? 0) * (float) $r->qty_returned, 2),
                'line_discount' => (float) $r->unit_discount_deducted, 'invoice_discount' => (float) $r->global_discount_clawback,
                'refund' => (float) $r->refund_amount,
            ])->values()->all(),
            'taxes' => array_values(array_map(fn ($t) => ['amount' => round($t['amount'], 2)] + $t, $taxes)),
            'refund' => round((float) $rows->sum('refund_amount'), 2),
            'legacy' => $legacy,
        ];
    }
}
