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

    public function show($return_no)
    {
        $returns = InvoiceItemReturn::where('return_no', $return_no)
            ->with(['invoice.items.category', 'invoice.items.returns', 'product.strength', 'invoice.histories', 'invoice.returnHistories'])
            ->get();

        if ($returns->isEmpty()) {
            return abort(404, 'Return not found');
        }

        $firstReturn = $returns->first();
        $invoice = $firstReturn->invoice;

        $items = [];
        $totalClawback = 0;
        $totalUnitDiscount = 0;
        $grossSubtotal = 0;
        $totalReturn = 0;

        foreach ($returns as $ret) {
            $product = $ret->product;
            if (!$product) continue;

            $invoiceItem = $invoice->items->where('product_id', $ret->product_id)->first();
            $price = $invoiceItem ? $invoiceItem->price : $product->price;

            $grossTotal = $price * $ret->qty_returned;
            $netTotal = $grossTotal - $ret->unit_discount_deducted;

            $items[] = [
                'name' => $product->product_name,
                'strength' => $product->strength ? $product->strength->name : '',
                'category_name' => ($invoiceItem && $invoiceItem->category) ? $invoiceItem->category->name : '',
                'qty' => $ret->qty_returned,
                'price' => $price,
                'gross_total' => $grossTotal,
                'total' => $netTotal
            ];

            $totalClawback += $ret->global_discount_clawback;
            $totalUnitDiscount += $ret->unit_discount_deducted;
            $grossSubtotal += $grossTotal;
            $totalReturn += $netTotal;
        }

        $returnHistories = \App\Models\ReturnHistory::where('return_no', $return_no)->with('user')->get();

        return view('admin.returns.show', compact('return_no', 'invoice', 'items', 'totalClawback', 'totalUnitDiscount', 'grossSubtotal', 'totalReturn', 'returnHistories'));
    }

    public function printReturn($return_no)
    {
        $returns = InvoiceItemReturn::where('return_no', $return_no)->with(['invoice.items.category', 'product.strength'])->get();

        if ($returns->isEmpty()) {
            return response('Return Receipt not found', 404);
        }

        $firstReturn = $returns->first();
        $invoice_no = $firstReturn->invoice ? $firstReturn->invoice->invoice_no : 'Unknown';
        $invoice_date = $firstReturn->invoice ? $firstReturn->invoice->created_at->format('d-M-y g:ia') : null;

        $items = [];
        $totalClawback = 0;
        $totalUnitDiscount = 0;
        $grossSubtotal = 0;
        $totalReturn = 0;

        foreach ($returns as $ret) {
            $product = $ret->product;
            if (!$product) continue;

            $invoiceItem = $ret->invoice->items->where('product_id', $ret->product_id)->first();
            $price = $invoiceItem ? $invoiceItem->price : $product->price;

            $grossTotal = $price * $ret->qty_returned;
            $netTotal = $grossTotal - $ret->unit_discount_deducted;

            $items[] = [
                'name' => $product->product_name,
                'strength' => $product->strength ? $product->strength->name : '',
                'category_name' => ($invoiceItem && $invoiceItem->category) ? $invoiceItem->category->name : '',
                'qty' => $ret->qty_returned,
                'price' => $price,
                'gross_total' => $grossTotal,
                'total' => $netTotal
            ];

            $totalClawback += $ret->global_discount_clawback;
            $totalUnitDiscount += $ret->unit_discount_deducted;
            $grossSubtotal += $grossTotal;
            $totalReturn += $netTotal;
        }

        $metadata = [
            'global_discount_clawback' => $totalClawback,
            'total_unit_discount' => $totalUnitDiscount,
            'gross_subtotal' => $grossSubtotal
        ];

        return view('admin.invoices.return-receipt-print', [
            'invoice_no' => $invoice_no,
            'invoice_date' => $invoice_date,
            'return_no'  => $return_no,
            'returnedItems' => $items,
            'metadata' => $metadata,
            'totalReturn' => $totalReturn
        ]);
    }
}
