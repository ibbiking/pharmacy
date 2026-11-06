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
    public function report(Request $request)
    {
        $fromDate = $request->input('from_date', Carbon::now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', Carbon::now()->endOfMonth()->toDateString());

        if ($request->ajax()) {
            $query = InvoiceItemReturn::with(['invoice', 'product', 'user'])
                ->select('invoice_item_returns.*')
                ->orderBy('created_at', 'desc');

            if ($fromDate && $toDate) {
                $fDate = Carbon::parse($fromDate)->startOfDay();
                $tDate = Carbon::parse($toDate)->endOfDay();
                $query->whereBetween('created_at', [$fDate, $tDate]);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->filterColumn('invoice_no', function ($query, $keyword) {
                    $query->whereHas('invoice', function ($q) use ($keyword) {
                        $q->where('invoice_no', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('product_name', function ($query, $keyword) {
                    $query->whereHas('product', function ($q) use ($keyword) {
                        $q->where('product_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('date', function ($query, $keyword) {
                    $query->whereRaw("DATE_FORMAT(invoice_item_returns.created_at, '%d %b, %Y %h:%i %p') like ?", ["%$keyword%"]);
                })
                ->addColumn('invoice_no', function ($row) {
                    return $row->invoice ? $row->invoice->invoice_no : 'N/A';
                })
                ->addColumn('product_name', function ($row) {
                    $strength = $row->product && $row->product->strength ? ' (' . $row->product->strength->name . ')' : '';
                    return $row->product ? $row->product->product_name . $strength : 'N/A';
                })
                ->addColumn('total_refund', function ($row) {
                    if (!$row->product) return '0.00';
                    $invoiceItem = $row->invoice->items->where('product_id', $row->product_id)->first();
                    $price = $invoiceItem ? $invoiceItem->price : $row->product->price;
                    
                    $gross = $price * $row->qty_returned;
                    $net = $gross - $row->unit_discount_deducted - $row->global_discount_clawback;
                    return number_format($net, 2);
                })
                ->addColumn('date', function ($row) {
                    return $row->created_at->format('d M, Y h:i A');
                })
                ->addColumn('action', function ($row) {
                    $buttons = '';
                    if ($row->return_no) {
                        $buttons .= '<a href="'.route('returns.show', $row->return_no).'" class="btn btn-sm btn-info text-white mr-2" style="margin-right: 5px;"><i class="fas fa-eye"></i> View</a>';
                    }
                    if (!$row->return_no) {
                        $buttons .= '<span class="text-muted"><small>Legacy (No Receipt)</small></span>';
                    } else {
                        $printUrl = route('returns.print', $row->return_no);
                        $buttons .= '<a href="javascript:void(0)" onclick="printReturnReceipt(\''.$printUrl.'\')" class="btn btn-sm btn-outline-primary"><i class="fas fa-print"></i> Print Receipt</a>';
                    }
                    return $buttons;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('admin.returns.report', compact('fromDate', 'toDate'));
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
