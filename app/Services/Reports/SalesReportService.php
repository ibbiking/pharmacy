<?php

namespace App\Services\Reports;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemReturn;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Money reports (Roadmap 5.9a). Sums run in SQL over the current business (BusinessScope); nothing loads a whole table.
 *  - net sales = the invoices' grand totals now (returns already taken off), discounts = line + invoice discounts;
 *  - refunds = the cash refunds stored on each return (snapshot), by the return's own date;
 *  - cost of goods = what the batches actually sold cost (invoice_item_lots: paid cost per base unit × base units still
 *    sold; + the purchase tax per unit when the line's price included tax). Lines sold before that ledger existed use
 *    the previous estimate (legacyBaseUnitCost) and are counted in `estimated_lines`.
 */
class SalesReportService
{
    /** Always one business, also for a super admin (BusinessScope is skipped for them unless impersonating). */
    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function range(?string $preset, ?string $from, ?string $to): array
    {
        $today = today();

        return match ($preset) {
            'today' => [$today->copy(), $today->copy()->endOfDay()],
            '7d' => [$today->copy()->subDays(6), $today->copy()->endOfDay()],
            '30d' => [$today->copy()->subDays(29), $today->copy()->endOfDay()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [Carbon::parse($from ?: $today)->startOfDay(), Carbon::parse($to ?: $today)->endOfDay()],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()], // this month
        };
    }

    public function sales(Carbon $from, Carbon $to): array
    {
        $inv = Invoice::query()->where('invoices.business_id', $this->businessId())->whereBetween('created_at', [$from, $to]);
        $totals = (clone $inv)->selectRaw('count(*) as n, coalesce(sum(grand_total),0) as net, coalesce(sum(discount + invoice_discount_amount),0) as disc, coalesce(sum(tax_amount),0) as tax')->first();
        $refunds = InvoiceItemReturn::query()->where('invoice_item_returns.business_id', $this->businessId())->whereBetween('created_at', [$from, $to])
            ->selectRaw('count(distinct return_no) as n, coalesce(sum(refund_amount),0) as total')->first();

        $sales = (clone $inv)->selectRaw('date(created_at) as d, sum(grand_total) as v')->groupBy('d')->pluck('v', 'd');
        $refundDays = InvoiceItemReturn::query()->where('invoice_item_returns.business_id', $this->businessId())->whereBetween('created_at', [$from, $to])->selectRaw('date(created_at) as d, sum(refund_amount) as v')->groupBy('d')->pluck('v', 'd');

        return [
            'kpis' => [
                'invoices' => (int) $totals->n, 'net_sales' => round((float) $totals->net, 2), 'discounts' => round((float) $totals->disc, 2),
                'sale_tax' => round((float) $totals->tax, 2), 'average' => $totals->n ? round((float) $totals->net / $totals->n, 2) : 0.0,
                'returns' => (int) $refunds->n, 'refunds' => round((float) $refunds->total, 2),
            ],
            'series' => $this->days($from, $to, ['sales' => $sales, 'refunds' => $refundDays]),
        ];
    }

    /** Best sellers of the period (paged): quantity in base units, revenue after line discounts. */
    public function bestSellers(Carbon $from, Carbon $to, int $perPage = 10)
    {
        return InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.business_id', $this->businessId())->whereBetween('invoices.created_at', [$from, $to])->whereNull('invoices.deleted_at')
            ->selectRaw('invoice_items.product_id, max(invoice_items.name) as name, sum(invoice_items.row_total) as revenue, sum(invoice_items.base_quantity) as base_qty, count(*) as line_count')
            ->groupBy('invoice_items.product_id')->orderByDesc('revenue')->paginate($perPage, ['*'], 'page')->withQueryString();
    }

    public function profit(Carbon $from, Carbon $to): array
    {
        $rows = $this->profitByProduct($from, $to);
        $revenue = $rows->sum('revenue');
        $cost = $rows->sum('cost');

        return [
            'kpis' => [
                'revenue' => round($revenue, 2), 'cost' => round($cost, 2), 'profit' => round($revenue - $cost, 2),
                'margin' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
                'estimated_lines' => (int) $rows->sum('estimated_lines'),
            ],
            'series' => $this->profitDays($from, $to),
            'products' => $rows->sortByDesc('profit')->values(),
        ];
    }

    /** Revenue and cost per product (one row per product sold in the period; aggregated, not raw lines). */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        $revenueExpr = 'invoice_items.row_total * (1 - case when invoices.total > 0 then invoices.invoice_discount_amount / invoices.total else 0 end)';
        $lines = fn () => InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.business_id', $this->businessId())->whereBetween('invoices.created_at', [$from, $to])->whereNull('invoices.deleted_at');

        $revenue = $lines()->selectRaw("invoice_items.product_id, max(invoice_items.name) as name, sum({$revenueExpr}) as revenue")
            ->groupBy('invoice_items.product_id')->get()->keyBy('product_id');
        $ledgerCost = $lines()->join('invoice_item_lots', 'invoice_item_lots.invoice_item_id', '=', 'invoice_items.id')->whereNull('invoice_item_lots.deleted_at')
            ->selectRaw('invoice_items.product_id, sum((invoice_item_lots.base_qty - invoice_item_lots.returned_base_qty) * (invoice_item_lots.base_unit_cost + case when invoice_items.is_tax_included then invoice_item_lots.base_unit_purchase_tax else 0 end)) as cost')
            ->groupBy('invoice_items.product_id')->pluck('cost', 'invoice_items.product_id');

        // Older lines (no lot ledger): previous estimate, per line. Their number never grows (every new sale has lots).
        $legacy = [];
        $lines()->whereNotExists(fn ($q) => $q->selectRaw('1')->from('invoice_item_lots')->whereColumn('invoice_item_lots.invoice_item_id', 'invoice_items.id'))
            ->select('invoice_items.*')->with('returns')->chunkById(200, function ($items) use (&$legacy) {
                foreach ($items as $item) {
                    $ratio = (float) $item->qty > 0 ? max(0, (float) $item->qty - (float) $item->returns->sum('qty_returned')) / (float) $item->qty : 0;
                    $legacy[$item->product_id]['cost'] = ($legacy[$item->product_id]['cost'] ?? 0) + (float) $item->base_quantity * $ratio * $this->legacyBaseUnitCost($item);
                    $legacy[$item->product_id]['lines'] = ($legacy[$item->product_id]['lines'] ?? 0) + 1;
                }
            }, 'invoice_items.id', 'id');

        return $revenue->map(fn ($r) => (object) [
            'product_id' => (int) $r->product_id, 'name' => $r->name, 'revenue' => round((float) $r->revenue, 2),
            'cost' => round((float) ($ledgerCost[$r->product_id] ?? 0) + (float) ($legacy[$r->product_id]['cost'] ?? 0), 2),
            'estimated_lines' => (int) ($legacy[$r->product_id]['lines'] ?? 0),
        ])->map(function ($r) {
            $r->profit = round($r->revenue - $r->cost, 2);
            $r->margin = $r->revenue > 0 ? round($r->profit / $r->revenue * 100, 1) : null;

            return $r;
        })->values();
    }

    private function profitDays(Carbon $from, Carbon $to): array
    {
        $revenue = InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.business_id', $this->businessId())->whereBetween('invoices.created_at', [$from, $to])->whereNull('invoices.deleted_at')
            ->selectRaw('date(invoices.created_at) as d, sum(invoice_items.row_total * (1 - case when invoices.total > 0 then invoices.invoice_discount_amount / invoices.total else 0 end)) as v')
            ->groupBy('d')->pluck('v', 'd');
        $cost = InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('invoice_item_lots', 'invoice_item_lots.invoice_item_id', '=', 'invoice_items.id')
            ->where('invoices.business_id', $this->businessId())->whereBetween('invoices.created_at', [$from, $to])->whereNull('invoices.deleted_at')->whereNull('invoice_item_lots.deleted_at')
            ->selectRaw('date(invoices.created_at) as d, sum((invoice_item_lots.base_qty - invoice_item_lots.returned_base_qty) * (invoice_item_lots.base_unit_cost + case when invoice_items.is_tax_included then invoice_item_lots.base_unit_purchase_tax else 0 end)) as v')
            ->groupBy('d')->pluck('v', 'd');
        $profit = collect($revenue)->map(fn ($v, $d) => (float) $v - (float) ($cost[$d] ?? 0));

        return $this->days($from, $to, ['revenue' => $revenue, 'profit' => $profit]);
    }

    /** Fill every day of the range (max 400 days; longer ranges are grouped by month). */
    private function days(Carbon $from, Carbon $to, array $series): array
    {
        $byMonth = $from->diffInDays($to) > 400;
        $out = [];
        for ($d = $from->copy()->startOfDay(); $d <= $to; $d->addDay()) {
            $key = $byMonth ? $d->format('Y-m') : $d->toDateString();
            $out[$key] ??= ['label' => $byMonth ? $d->format('M Y') : $d->format('d M')] + array_fill_keys(array_keys($series), 0.0);
            foreach ($series as $name => $values) {
                $out[$key][$name] += round((float) ($values[$d->toDateString()] ?? 0), 2);
            }
        }

        return array_values($out);
    }

    /** Estimated cost per base unit for a line sold before the lot ledger existed (moved unchanged from ReportController). */
    public function legacyBaseUnitCost(InvoiceItem $item): float
    {
        // Actual cost: invoices saved since the lot ledger exists record the exact lots each line
        // consumed and what those units cost (paid cost incl. extra paid). Weighted by the quantity
        // still sold (net of returns). Older invoices fall back to the estimate below.
        $lots = $item->relationLoaded('lots') ? $item->lots : $item->lots()->get();
        if ($lots->isNotEmpty()) {
            $qty = 0.0;
            $cost = 0.0;
            foreach ($lots as $lot) {
                $net = $lot->netBaseQty();
                $unit = $lot->base_unit_cost + ($item->is_tax_included ? $lot->base_unit_purchase_tax : 0);
                $qty += $net;
                $cost += $net * $unit;
            }
            if ($qty > 0) {
                return $cost / $qty;
            }
            // Fully returned: average of the consumed lots so the (zero-qty) line still has a cost.
            $all = $lots->sum('base_qty');
            if ($all > 0) {
                return $lots->sum(fn ($l) => $l->base_qty * ($l->base_unit_cost + ($item->is_tax_included ? $l->base_unit_purchase_tax : 0))) / $all;
            }
        }

        $product_id = $item->product_id;
        $preference = $item->sale_preference_slug ?? 'static-price';
        $taxIncluded = $item->is_tax_included;

        $baseUnitCost = 0;

        // If stock-wise-price, try to find the exact purchase batch
        if ($preference === 'stock-wise-price') {
            $targetPrice = round((float)$item->base_category_price, 4);
            $stockRows = \App\Models\BaseStockSalePrice::where('product_id', $product_id)
                ->where('base_category_id', $item->base_category_id)
                ->orderBy('id', 'desc')
                ->get();
            
            $purchase_id = null;
            foreach ($stockRows as $row) {
                $rowPrice = (float)$row->base_category_unit_sale_price;
                if ($taxIncluded) {
                    $rowPrice += (float)($row->base_category_unit_sale_tax_price ?? 0);
                }
                if (abs($rowPrice - $targetPrice) < 0.001) {
                    $purchase_id = $row->purchase_id;
                    break;
                }
            }
            
            if ($purchase_id) {
                $purchase = Purchase::find($purchase_id);
                if ($purchase) {
                    $paidTotal = $purchase->paid_extra_total_cost_price > 0 ? $purchase->paid_extra_total_cost_price : $purchase->total_cost_price;
                    $basePaidUnit = $purchase->base_quantity > 0 ? ($paidTotal / $purchase->base_quantity) : 0;
                    
                    $baseUnitCost = $taxIncluded ? ($basePaidUnit + $purchase->base_unit_purchase_tax_price) : $basePaidUnit;
                }
            }
        }
        
        // For static-price, check ProductParameter for static_category_unit_purchase_price
        if ($preference === 'static-price') {
            $param = \App\Models\ProductParameter::where('product_id', $product_id)
                ->where('child_category_id', $item->base_category_id)
                ->first();
                
            if ($param && $param->static_category_unit_purchase_price > 0) {
                // Return exactly what's configured, assuming user configured it.
                $baseUnitCost = $param->static_category_unit_purchase_price;
            }
        }
        
        // For previous-inventory-price, or if stock-wise/static failed to find a match:
        if ($baseUnitCost == 0) {
            $lastPurchase = Purchase::where('product_id', $product_id)
                ->latest('created_at')
                ->first();
                
            if ($lastPurchase) {
                 $paidTotal = $lastPurchase->paid_extra_total_cost_price > 0 ? $lastPurchase->paid_extra_total_cost_price : $lastPurchase->total_cost_price;
                 $basePaidUnit = $lastPurchase->base_quantity > 0 ? ($paidTotal / $lastPurchase->base_quantity) : 0;
                 
                 $baseUnitCost = $taxIncluded ? ($basePaidUnit + $lastPurchase->base_unit_purchase_tax_price) : $basePaidUnit;
            }
        }
        
        return (float) $baseUnitCost;
    }
}
