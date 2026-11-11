<?php

namespace App\Http\Controllers\Admin\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Advanced Reporting (Roadmap step 12): dashboard, CSV exports, weekly summary email setting. */
class AnalyticsController extends Controller
{
    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    private function range(Request $request): array
    {
        $from = $request->date('from')?->toDateString() ?? now()->subDays(29)->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        // At most two years in one view (keeps every query bounded).
        if (\Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to)) > 731) {
            $from = \Carbon\Carbon::parse($to)->subDays(731)->toDateString();
        }

        return [$from, $to];
    }

    public function index(Request $request, AnalyticsService $analytics)
    {
        [$from, $to] = $this->range($request);

        return Inertia::render('Analytics/Index', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => Inertia::defer(fn () => $analytics->summary($this->businessId(), $from, $to)),
            'weekly' => (bool) DB::table('preferences')->where('business_id', $this->businessId())->where('type', 'analytics')->where('slug', 'weekly-summary')->whereNull('deleted_at')->value('status'),
            'currency' => currency_symbol(),
        ]);
    }

    /** CSV of one report for the period (streamed, never loaded whole). */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $b = $this->businessId();
        $report = in_array($request->input('report'), ['pos_sales', 'b2b_documents', 'purchases', 'expenses', 'payments'], true) ? $request->input('report') : 'pos_sales';
        [$columns, $query] = match ($report) {
            'pos_sales' => [['invoice_no', 'date', 'subtotal', 'discount', 'tax', 'grand_total'], DB::table('invoices')->where('business_id', $b)->whereNull('deleted_at')
                ->whereBetween(DB::raw('date(created_at)'), [$from, $to])->orderBy('id')->selectRaw('invoice_no, created_at as date, subtotal, discount + invoice_discount_amount as discount, tax_amount as tax, grand_total')],
            'b2b_documents' => [['type', 'number', 'date', 'due_date', 'status', 'total', 'balance'], DB::table('documents')->where('business_id', $b)->whereNull('deleted_at')
                ->whereBetween('date', [$from, $to])->orderBy('id')->select('type', 'number', 'date', 'due_date', 'status', 'total', 'balance')],
            'purchases' => [['date', 'invoice_no', 'batch_no', 'product', 'quantity', 'paid_total', 'expiry_date'], DB::table('purchases as p')->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
                ->where('p.business_id', $b)->whereNull('p.deleted_at')->whereBetween(DB::raw('date(p.created_at)'), [$from, $to])->orderBy('p.id')
                ->selectRaw('p.created_at as date, p.invoice_no, p.batch_no, pr.product_name as product, p.quantity, case when p.paid_extra_total_cost_price > 0 then p.paid_extra_total_cost_price else p.total_cost_price end as paid_total, p.expiry_date')],
            'expenses' => [['number', 'date', 'merchant', 'amount', 'tax_amount', 'paid_by', 'status'], DB::table('expenses')->where('business_id', $b)->whereNull('deleted_at')
                ->whereBetween('date', [$from, $to])->orderBy('id')->select('number', 'date', 'merchant', 'amount', 'tax_amount', 'paid_by', 'status')],
            'payments' => [['number', 'direction', 'date', 'amount', 'unapplied', 'method', 'reference'], DB::table('payments')->where('business_id', $b)->whereNull('deleted_at')
                ->whereBetween('date', [$from, $to])->orderBy('id')->select('number', 'direction', 'date', 'amount', 'unapplied', 'method', 'reference')],
        };

        return response()->streamDownload(function () use ($columns, $query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($query->cursor() as $row) {
                // Values starting with = + - @ are prefixed so spreadsheets don't run them as formulas.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, array_values((array) $row)));
            }
            fclose($out);
        }, "{$report}_{$from}_{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    public function weekly(Request $request)
    {
        abort_unless($request->user()->isOwnerOf($this->businessId()), 403);
        $on = $request->boolean('weekly');
        $q = DB::table('preferences')->where('business_id', $this->businessId())->where('type', 'analytics')->where('slug', 'weekly-summary')->whereNull('deleted_at');
        $q->exists() ? $q->update(['status' => $on, 'updated_at' => now()])
            : DB::table('preferences')->insert(['business_id' => $this->businessId(), 'type' => 'analytics', 'slug' => 'weekly-summary', 'preference' => 'Weekly summary email to owners',
                'status' => $on, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', $on ? 'Owners will get a summary every Monday morning.' : 'Weekly summary email switched off.');
    }
}
