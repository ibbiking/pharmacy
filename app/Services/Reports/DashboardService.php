<?php

namespace App\Services\Reports;

use App\Models\BaseStockSalePrice;
use App\Models\Invoice;
use App\Models\InvoiceHistory;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemReturn;
use App\Models\Tax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The owner's dashboard (Roadmap 5.10). Built on SalesReportService / StockReportService so every number matches the
 * reports. All queries are pinned to the current business; each method feeds one deferred panel.
 */
class DashboardService
{
    public function __construct(private readonly SalesReportService $sales, private readonly StockReportService $stock)
    {
    }

    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    /** KPIs for the period and the previous period of the same length (for the trend arrows). */
    public function kpis(Carbon $from, Carbon $to): array
    {
        $days = max(1, (int) round($from->diffInDays($to)) + 1);
        [$pFrom, $pTo] = [$from->copy()->subDays($days), $from->copy()->subSecond()];
        $now = $this->periodKpis($from, $to);
        $before = $this->periodKpis($pFrom, $pTo);

        $out = [];
        foreach ($now as $k => $v) {
            $prev = $before[$k] ?? 0;
            $out[$k] = ['value' => $v, 'previous' => $prev, 'change' => $prev != 0 ? round(($v - $prev) / abs($prev) * 100, 1) : null];
        }
        $out['stock_value'] = ['value' => $this->stockValue(), 'previous' => null, 'change' => null];

        return $out + ['previous_label' => $pFrom->format('d M').' – '.$pTo->format('d M')];
    }

    private function periodKpis(Carbon $from, Carbon $to): array
    {
        $s = $this->sales->sales($from, $to)['kpis'];
        $p = $this->sales->profit($from, $to)['kpis'];

        return ['net_sales' => $s['net_sales'], 'invoices' => $s['invoices'], 'average' => $s['average'], 'profit' => $p['profit'], 'refunds' => $s['refunds']];
    }

    /** Sellable stock (not expired) at what it cost. */
    public function stockValue(): float
    {
        return round((float) BaseStockSalePrice::query()->where('base_stock_sale_price.business_id', $this->businessId())
            ->where('remaining_base_stock', '>', 0)->where(fn ($w) => $w->whereNull('base_stock_sale_price.expiry_date')->orWhereDate('base_stock_sale_price.expiry_date', '>', today()))
            ->join('purchases', 'purchases.id', '=', 'base_stock_sale_price.purchase_id')
            ->sum(DB::raw('remaining_base_stock * (case when purchases.base_quantity > 0 then (case when purchases.paid_extra_total_cost_price > 0 then purchases.paid_extra_total_cost_price else purchases.total_cost_price end) / purchases.base_quantity else 0 end)')), 2);
    }

    public function charts(Carbon $from, Carbon $to): array
    {
        $heat = Invoice::query()->where('invoices.business_id', $this->businessId())->whereBetween('created_at', [$from, $to])
            ->selectRaw('(dayofweek(created_at) + 5) % 7 as wd, hour(created_at) as h, sum(grand_total) as v, count(*) as n') // Monday = 0
            ->groupBy('wd', 'h')->get()->map(fn ($r) => [(int) $r->h, (int) $r->wd, round((float) $r->v, 2), (int) $r->n])->values();
        $expiry = $this->stock->expiryStats();

        return [
            'series' => $this->sales->sales($from, $to)['series'],
            'heat' => $heat,
            'top' => collect($this->sales->bestSellers($from, $to, 5)->items())->map(fn ($r) => ['product_id' => (int) $r->product_id, 'name' => $r->name, 'revenue' => round((float) $r->revenue, 2)])->values(),
            'expiry' => [
                ['label' => 'Expired', 'value' => $expiry['expired']['value']], ['label' => '≤ 30 days', 'value' => $expiry['30']['value']],
                ['label' => '31–90 days', 'value' => $expiry['90']['value']], ['label' => 'Later', 'value' => $expiry['later']['value']],
            ],
        ];
    }

    public function panels(): array
    {
        $alerts = $this->stock->alertRows();
        $low = $alerts->filter(fn ($r) => $r->threshold !== null && $r->stock <= $r->threshold)->sortBy(fn ($r) => $r->threshold > 0 ? $r->stock / $r->threshold : 0)->take(5)
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'stock' => round($r->stock / $r->unit_size, 2), 'alert' => $r->alert_qty, 'unit' => $r->unit])->values();
        $expiring = $this->stock->expiryQuery('30')->orderBy('base_stock_sale_price.expiry_date')->limit(5)->get()
            ->map(fn ($l) => ['product_id' => $l->product_id, 'product' => $l->product_name, 'batch' => $l->batch_no, 'remaining' => (float) $l->remaining_base_stock,
                'days' => (int) today()->diffInDays(Carbon::parse($l->expiry_date), false), 'value' => round((float) $l->value, 2)]);

        return [
            'low' => $low,
            'expiring' => $expiring,
            'invoices' => Invoice::query()->where('invoices.business_id', $this->businessId())->latest('id')->limit(5)->get(['id', 'invoice_no', 'grand_total', 'created_at', 'fully_returned'])
                ->map(fn ($i) => ['invoice_no' => $i->invoice_no, 'total' => (float) $i->grand_total, 'at' => $i->created_at?->diffForHumans(), 'returned' => (bool) $i->fully_returned]),
            'returns' => InvoiceItemReturn::query()->where('invoice_item_returns.business_id', $this->businessId())
                ->selectRaw('return_no, max(created_at) as at, sum(refund_amount) as refund')->groupBy('return_no')->orderByDesc('at')->limit(5)->get()
                ->map(fn ($r) => ['return_no' => $r->return_no, 'refund' => round((float) $r->refund, 2), 'at' => Carbon::parse($r->at)->diffForHumans()]),
        ];
    }

    /** "What to do next": each suggestion is a count plus where to act on it. */
    public function suggestions(): array
    {
        $since = now()->subDays(30);
        $alerts = $this->stock->alertRows();
        $low = $alerts->filter(fn ($r) => $r->threshold !== null && $r->stock <= $r->threshold);

        // Reorder quantity: 30 days of the last 30 days' average sales, minus what is left (in the alert unit).
        $sold = InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')->where('invoices.business_id', $this->businessId())
            ->where('invoices.created_at', '>=', $since)->whereIn('invoice_items.product_id', $low->pluck('id'))
            ->selectRaw('invoice_items.product_id, sum(invoice_items.base_quantity) as q')->groupBy('invoice_items.product_id')->pluck('q', 'invoice_items.product_id');
        $reorder = $low->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'unit' => $r->unit,
            'suggested' => max((float) ($r->alert_qty ?? 0), ceil(max(0, (float) ($sold[$r->id] ?? 0) - $r->stock) / max(1, $r->unit_size)))])->sortByDesc('suggested')->take(5)->values();

        $belowCost = InvoiceItem::query()->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')->join('invoice_item_lots', 'invoice_item_lots.invoice_item_id', '=', 'invoice_items.id')
            ->where('invoices.business_id', $this->businessId())->where('invoices.created_at', '>=', $since)->where('invoice_items.base_quantity', '>', 0)
            ->whereRaw('(invoice_items.row_total / invoice_items.base_quantity) < invoice_item_lots.base_unit_cost')->distinct()->count('invoice_items.id');
        $overrides = InvoiceHistory::query()->join('invoices', 'invoices.id', '=', 'invoice_histories.invoice_id')->leftJoin('users', 'users.id', '=', 'invoice_histories.user_id')
            ->where('invoices.business_id', $this->businessId())->where('invoice_histories.action', 'Price Override')->where('invoice_histories.created_at', '>=', $since)
            ->selectRaw('coalesce(users.name, ?) as name, count(*) as n', ['Unknown'])->groupBy('users.name')->orderByDesc('n')->limit(5)->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->n]);
        $short = $this->stock->expiryQuery(null)->whereDate('base_stock_sale_price.expiry_date', '>', today())->whereDate('base_stock_sale_price.expiry_date', '<=', today()->addDays(60))->count();
        $unusedTaxes = Tax::query()->where('taxes.business_id', $this->businessId())->whereNotExists(fn ($q) => $q->selectRaw('1')->from('purchase_taxes')->whereColumn('purchase_taxes.tax_id', 'taxes.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('sale_taxes')->whereColumn('sale_taxes.tax_id', 'taxes.id'))->count();

        return [
            'reorder' => $reorder,
            'short_dated' => $short,
            'below_cost' => $belowCost,
            'overrides' => $overrides,
            'no_alert' => $alerts->filter(fn ($r) => $r->threshold === null)->count(),
            'unused_taxes' => $unusedTaxes,
        ];
    }
}
