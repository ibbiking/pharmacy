<?php

namespace App\Services\Analytics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Cross-module figures for one business and period (Advanced Reporting, Roadmap step 12). Every figure is summed in SQL
 * and comes with the same figure for the previous period of equal length. Modules that are off simply show 0.
 */
class AnalyticsService
{
    public function summary(int $businessId, string $from, string $to): array
    {
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $prevTo = Carbon::parse($from)->subDay()->toDateString();
        $prevFrom = Carbon::parse($prevTo)->subDays((int) $days - 1)->toDateString();
        $now = $this->figures($businessId, $from, $to);
        $before = $this->figures($businessId, $prevFrom, $prevTo);

        $kpis = [];
        foreach ($now as $key => $value) {
            $prev = $before[$key] ?? 0;
            $kpis[$key] = ['value' => $value, 'previous' => $prev, 'change' => $prev != 0 ? round(($value - $prev) / abs($prev) * 100, 1) : null];
        }

        return [
            'period' => ['from' => $from, 'to' => $to, 'previous_from' => $prevFrom, 'previous_to' => $prevTo],
            'kpis' => $kpis,
            'balances' => $this->balances($businessId),
            'series' => $this->series($businessId, $from, $to),
            'top_products' => $this->topProducts($businessId, $from, $to),
            'top_customers' => $this->topCustomers($businessId, $from, $to),
        ];
    }

    /** Totals of one period. */
    public function figures(int $b, string $from, string $to): array
    {
        $range = fn ($q, string $col) => $q->where("{$col}", '>=', "{$from} 00:00:00")->where("{$col}", '<=', "{$to} 23:59:59");
        $pos = (float) $range(DB::table('invoices')->where('business_id', $b)->whereNull('deleted_at'), 'created_at')->sum('grand_total');
        $posCost = (float) $range(DB::table('invoice_item_lots as l')->join('invoice_items as i', 'i.id', '=', 'l.invoice_item_id')->join('invoices as v', 'v.id', '=', 'i.invoice_id')
            ->where('v.business_id', $b)->whereNull('v.deleted_at')->whereNull('l.deleted_at'), 'v.created_at')->selectRaw('coalesce(sum((l.base_qty - l.returned_base_qty) * l.base_unit_cost), 0) as c')->value('c');
        $posTax = (float) $range(DB::table('invoices')->where('business_id', $b)->whereNull('deleted_at'), 'created_at')->sum(DB::raw('tax_amount + included_tax_amount'));
        $docs = fn (array $types) => DB::table('documents')->where('business_id', $b)->whereNull('deleted_at')->whereIn('type', $types)->whereNotIn('status', ['draft', 'void'])->whereBetween('date', [$from, $to]);
        $b2b = (float) $docs(['sales_invoice'])->sum(DB::raw('total - tax_total')) - (float) $docs(['credit_note'])->sum(DB::raw('total - tax_total'));
        $b2bCost = (float) $docs(['sales_invoice'])->sum('cost_total') - (float) $docs(['credit_note'])->sum('cost_total');
        $purchases = (float) $range(DB::table('purchases')->where('business_id', $b)->whereNull('deleted_at'), 'created_at')
            ->sum(DB::raw('case when paid_extra_total_cost_price > 0 then paid_extra_total_cost_price else total_cost_price end'));
        $expenses = (float) DB::table('expenses')->where('business_id', $b)->whereNull('deleted_at')->whereIn('status', ['approved', 'reimbursed'])->whereBetween('date', [$from, $to])->sum('amount');
        $received = (float) DB::table('payments')->where('business_id', $b)->whereNull('deleted_at')->where('direction', 'in')->whereBetween('date', [$from, $to])->sum('amount');
        $posNet = $pos - $posTax;
        $revenue = $posNet + $b2b;
        $gross = $revenue - $posCost - $b2bCost;

        return array_map(fn ($v) => round($v, 2), [
            'revenue' => $revenue, 'pos_sales' => $posNet, 'b2b_sales' => $b2b, 'gross_profit' => $gross, 'purchases' => $purchases,
            'expenses' => $expenses, 'net_profit' => $gross - $expenses, 'payments_received' => $received,
            'invoices' => (float) $range(DB::table('invoices')->where('business_id', $b)->whereNull('deleted_at'), 'created_at')->count() + (float) $docs(['sales_invoice'])->count(),
        ]);
    }

    private function balances(int $b): array
    {
        $open = fn (string $type) => round((float) DB::table('documents')->where('business_id', $b)->whereNull('deleted_at')->where('type', $type)->whereIn('status', ['unpaid', 'partial'])->sum('balance'), 2);
        $overdue = fn (string $type) => round((float) DB::table('documents')->where('business_id', $b)->whereNull('deleted_at')->where('type', $type)->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->sum('balance'), 2);
        $stock = (float) DB::table('base_stock_sale_price as l')->join('purchases as p', 'p.id', '=', 'l.purchase_id')->where('l.business_id', $b)->whereNull('l.deleted_at')
            ->where('l.remaining_base_stock', '>', 0)->selectRaw('coalesce(sum(l.remaining_base_stock * (case when p.paid_extra_total_cost_price > 0 then p.paid_extra_total_cost_price else p.total_cost_price end) / nullif(p.base_quantity, 0)), 0) as v')->value('v');
        $cash = null;
        if (DB::table('accounts')->where('business_id', $b)->exists()) {
            $cash = round((float) DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')->where('l.business_id', $b)->whereNull('l.deleted_at')
                ->whereIn('a.sub_type', ['cash', 'bank'])->selectRaw('coalesce(sum(l.debit - l.credit), 0) as v')->value('v'), 2);
        }

        return ['receivables' => $open('sales_invoice'), 'receivables_overdue' => $overdue('sales_invoice'), 'payables' => $open('bill'), 'payables_overdue' => $overdue('bill'),
            'stock_value' => round($stock, 2), 'cash' => $cash];
    }

    private function series(int $b, string $from, string $to): array
    {
        $pos = DB::table('invoices')->where('business_id', $b)->whereNull('deleted_at')->whereBetween(DB::raw('date(created_at)'), [$from, $to])
            ->groupBy(DB::raw('date(created_at)'))->selectRaw('date(created_at) as d, sum(grand_total - tax_amount - included_tax_amount) as v')->pluck('v', 'd');
        $b2b = DB::table('documents')->where('business_id', $b)->whereNull('deleted_at')->where('type', 'sales_invoice')->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('date', [$from, $to])->groupBy('date')->selectRaw('date as d, sum(total - tax_total) as v')->pluck('v', 'd');
        $exp = DB::table('expenses')->where('business_id', $b)->whereNull('deleted_at')->whereIn('status', ['approved', 'reimbursed'])->whereBetween('date', [$from, $to])
            ->groupBy('date')->selectRaw('date as d, sum(amount) as v')->pluck('v', 'd');
        $labels = [];
        $out = ['pos' => [], 'b2b' => [], 'expenses' => []];
        $days = Carbon::parse($from)->diffInDays(Carbon::parse($to));
        $step = $days > 120 ? 'month' : 'day';
        $cursor = Carbon::parse($from);
        $end = Carbon::parse($to);
        $bucket = [];
        while ($cursor->lte($end)) {
            $key = $step === 'month' ? $cursor->format('M Y') : $cursor->format('d M');
            $d = $cursor->toDateString();
            $bucket[$key]['pos'] = ($bucket[$key]['pos'] ?? 0) + (float) ($pos[$d] ?? 0);
            $bucket[$key]['b2b'] = ($bucket[$key]['b2b'] ?? 0) + (float) ($b2b[$d] ?? 0);
            $bucket[$key]['expenses'] = ($bucket[$key]['expenses'] ?? 0) + (float) ($exp[$d] ?? 0);
            $cursor->addDay();
        }
        foreach ($bucket as $label => $v) {
            $labels[] = $label;
            foreach ($v as $k => $x) {
                $out[$k][] = round($x, 2);
            }
        }

        return ['labels' => $labels] + $out;
    }

    private function topProducts(int $b, string $from, string $to): array
    {
        $pos = DB::table('invoice_items as i')->join('invoices as v', 'v.id', '=', 'i.invoice_id')->where('v.business_id', $b)->whereNull('v.deleted_at')->whereNull('i.deleted_at')
            ->whereBetween(DB::raw('date(v.created_at)'), [$from, $to])->groupBy('i.product_id')->selectRaw('i.product_id, sum(i.row_total) as revenue')->pluck('revenue', 'product_id');
        $b2b = DB::table('document_lines as l')->join('documents as d', 'd.id', '=', 'l.document_id')->where('d.business_id', $b)->whereNull('d.deleted_at')->where('d.type', 'sales_invoice')
            ->whereNotIn('d.status', ['draft', 'void'])->whereBetween('d.date', [$from, $to])->groupBy('l.product_id')->selectRaw('l.product_id, sum(l.net_amount) as revenue')->pluck('revenue', 'product_id');
        $total = [];
        foreach ([$pos, $b2b] as $set) {
            foreach ($set as $id => $v) {
                $total[$id] = ($total[$id] ?? 0) + (float) $v;
            }
        }
        arsort($total);
        $top = array_slice($total, 0, 8, true);
        $names = DB::table('products')->whereIn('id', array_keys($top))->pluck('product_name', 'id');

        return collect($top)->map(fn ($v, $id) => ['id' => (int) $id, 'name' => $names[$id] ?? '—', 'revenue' => round($v, 2)])->values()->all();
    }

    private function topCustomers(int $b, string $from, string $to): array
    {
        return DB::table('documents as d')->join('customers as c', 'c.id', '=', 'd.customer_id')->where('d.business_id', $b)->whereNull('d.deleted_at')->where('d.type', 'sales_invoice')
            ->whereNotIn('d.status', ['draft', 'void'])->whereBetween('d.date', [$from, $to])->groupBy('d.customer_id', 'c.name')
            ->selectRaw('d.customer_id as id, c.name, sum(d.total) as revenue, sum(d.balance) as open')->orderByDesc('revenue')->limit(8)->get()
            ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'revenue' => round((float) $r->revenue, 2), 'open' => round((float) $r->open, 2)])->all();
    }
}
