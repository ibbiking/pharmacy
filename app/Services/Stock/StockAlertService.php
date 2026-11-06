<?php

namespace App\Services\Stock;

use App\Events\StockReachedAlertLevel;
use App\Models\Invoice;
use App\Services\Reports\StockReportService;
use Illuminate\Support\Facades\DB;

/**
 * Roadmap 13c: after a sale, which products fell to (or below) their alert level IN THIS SALE.
 * "Crossing" only: stock before the sale was above the level and is now at or below it, so a product that is already
 * low doesn't alert again on every sale. Same rule as the Stock alerts report (StockReportService::alertRows).
 */
class StockAlertService
{
    public function __construct(private readonly StockReportService $reports)
    {
    }

    /** @return list<array{product_id:int, name:string, stock:float, alert_qty:float, unit:?string}> */
    public function crossedBy(Invoice $invoice, int $businessId): array
    {
        $sold = DB::table('invoice_item_lots')
            ->join('invoice_items', 'invoice_items.id', '=', 'invoice_item_lots.invoice_item_id')
            ->where('invoice_items.invoice_id', $invoice->id)->whereNull('invoice_item_lots.deleted_at')
            ->groupBy('invoice_items.product_id')
            ->selectRaw('invoice_items.product_id, sum(invoice_item_lots.base_qty) as qty')
            ->pluck('qty', 'product_id');
        if ($sold->isEmpty()) {
            return [];
        }

        $alerts = [];
        foreach ($this->reports->forBusiness($businessId)->alertRows($sold->keys()->map(fn ($id) => (int) $id)->all()) as $row) {
            if ($row->threshold === null || $row->broken) {
                continue;
            }
            $before = $row->stock + (float) ($sold[$row->id] ?? 0);
            if ($row->stock <= $row->threshold && $before > $row->threshold) {
                $alerts[] = [
                    'product_id' => $row->id,
                    'name' => $row->name,
                    'stock' => round($row->stock / ($row->unit_size ?: 1), 2),
                    'alert_qty' => (float) $row->alert_qty,
                    'unit' => $row->unit,
                ];
            }
        }

        return $alerts;
    }

    public function afterSale(Invoice $invoice, int $businessId): void
    {
        $alerts = $this->crossedBy($invoice, $businessId);
        if ($alerts) {
            StockReachedAlertLevel::dispatch($businessId, $invoice->invoice_no, $alerts);
        }
    }
}
