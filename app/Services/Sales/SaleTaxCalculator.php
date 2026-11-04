<?php

namespace App\Services\Sales;

use App\Models\Purchase;

/**
 * Tax breakdown of one sold line, attributed to the purchase lots the quantity actually comes from.
 *
 *  - GST (Paid Unit Cost markup %, `extra_paid_percent`) is DISCLOSURE ONLY: how much of the price
 *    is GST. Never changes what the customer pays.
 *  - Named Sale Taxes (SaleTax rows on the purchase):
 *      includeTax = false → ADDITIVE: amount = taxable × rate/100, added on top of the line total.
 *      includeTax = true  → INCLUDED (decision D3): the price already contains the tax;
 *                           amount = taxable × rate / (100 + sum of that lot's named rates).
 *                           Not added to the total; shown to owners only.
 */
class SaleTaxCalculator
{
    /** @var array<int, ?Purchase> */
    private array $purchases = [];

    /**
     * @param  array<int, array{lot: \App\Models\BaseStockSalePrice, qty: float}>  $allocations
     * @return array<int, array{tax_id: ?int, name: string, rate: float, amount: float, is_included: bool}>
     */
    public function breakdown(array $allocations, float $taxableAmount, bool $includeTax): array
    {
        $totalQty = array_sum(array_column($allocations, 'qty'));
        if ($totalQty <= 0 || $taxableAmount <= 0) {
            return [];
        }

        $buckets = [];

        foreach ($allocations as $allocation) {
            $purchase = $this->purchase((int) $allocation['lot']->purchase_id);
            if (! $purchase) {
                continue;
            }

            $chunk = $taxableAmount * ($allocation['qty'] / $totalQty);

            if ((float) $purchase->extra_paid_percent > 0) {
                $buckets['gst'] ??= ['tax_id' => null, 'name' => 'GST', 'amount' => 0.0, 'is_included' => false];
                $buckets['gst']['amount'] += $chunk * ((float) $purchase->extra_paid_percent / 100);
            }

            $namedRateSum = (float) $purchase->Saletaxes->sum('tax_rate');

            foreach ($purchase->Saletaxes as $saleTax) {
                $rate = (float) $saleTax->tax_rate;
                if ($rate <= 0) {
                    continue;
                }

                $key = 'tax_'.$saleTax->tax_id;
                $buckets[$key] ??= [
                    'tax_id' => (int) $saleTax->tax_id,
                    'name' => $saleTax->tax->name ?? 'Tax',
                    'amount' => 0.0,
                    'is_included' => $includeTax,
                ];

                $buckets[$key]['amount'] += $includeTax
                    ? $chunk * $rate / (100 + $namedRateSum)
                    : $chunk * $rate / 100;
            }
        }

        foreach ($buckets as &$bucket) {
            $bucket['rate'] = round(($bucket['amount'] / $taxableAmount) * 100, 4);
        }

        return array_values($buckets);
    }

    public static function additiveTotal(array $buckets): float
    {
        return array_sum(array_map(fn ($b) => ($b['tax_id'] !== null && ! $b['is_included']) ? $b['amount'] : 0, $buckets));
    }

    public static function includedTotal(array $buckets): float
    {
        return array_sum(array_map(fn ($b) => ($b['tax_id'] !== null && $b['is_included']) ? $b['amount'] : 0, $buckets));
    }

    public static function gstTotal(array $buckets): float
    {
        return array_sum(array_map(fn ($b) => $b['tax_id'] === null ? $b['amount'] : 0, $buckets));
    }

    private function purchase(int $purchaseId): ?Purchase
    {
        if (! array_key_exists($purchaseId, $this->purchases)) {
            $this->purchases[$purchaseId] = Purchase::withoutGlobalScopes()->with('Saletaxes.tax')->find($purchaseId);
        }

        return $this->purchases[$purchaseId];
    }
}
