<?php

namespace App\Services\Numbering;

use App\Models\NumberSeries;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hands out document numbers per business (Roadmap 4g). Safe under concurrency: the series row is
 * locked for the duration of the caller's transaction, so two tills never get the same number.
 * Numbers that already exist (e.g. legacy formats) are skipped.
 */
class NumberSeriesService
{
    /** document type => [default prefix, label, table, number column] (table null = no existence check) */
    public const TYPES = [
        'invoice' => ['INV-', 'Sale invoice', 'invoices', 'invoice_no'],
        'return' => ['RET-', 'Return', 'invoice_item_returns', 'return_no'],
        'quotation' => ['QT-', 'Quotation', null, null],
        'sales_order' => ['SO-', 'Sales order', null, null],
        'purchase_order' => ['PO-', 'Purchase order', null, null],
        'bill' => ['BL-', 'Bill', null, null],
        'payment_receipt' => ['PR-', 'Payment receipt', null, null],
        'credit_note' => ['CR-', 'Credit note', null, null],
        'expense' => ['EXP-', 'Expense', null, null],
    ];

    /** Document types in use today (the rest are created when their modules ship). */
    public const ACTIVE = ['invoice', 'return'];

    public function next(int $businessId, string $type): string
    {
        $this->assertType($type);

        return DB::transaction(function () use ($businessId, $type) {
            $series = $this->seriesForUpdate($businessId, $type);
            [, , $table, $column] = self::TYPES[$type];

            $number = $series->next_number;
            for ($guard = 0; $guard < 10000; $guard++) {
                $candidate = $series->format($number);
                $taken = $table && DB::table($table)->where('business_id', $businessId)->where($column, $candidate)->exists();
                if (! $taken) {
                    $series->next_number = $number + 1;
                    $series->save();

                    return $candidate;
                }
                $number++;
            }

            throw new \RuntimeException("Could not find a free {$type} number for business {$businessId}.");
        });
    }

    /** What the next number will look like (no reservation: cosmetic preview only). */
    public function peek(int $businessId, string $type): string
    {
        $this->assertType($type);
        $series = NumberSeries::query()->where('business_id', $businessId)->where('document_type', $type)->first()
            ?? new NumberSeries(['prefix' => self::TYPES[$type][0], 'next_number' => 1, 'padding' => 6]);
        [, , $table, $column] = self::TYPES[$type];

        // Same skipping as next(), so the preview matches what will actually be issued.
        $number = $series->next_number;
        while ($table && DB::table($table)->where('business_id', $businessId)->where($column, $series->format($number))->exists()) {
            $number++;
        }

        return $series->format($number);
    }

    /** Create the default series rows a business needs (idempotent). */
    public function ensureDefaults(int $businessId): void
    {
        foreach (self::ACTIVE as $type) {
            NumberSeries::withTrashed()->firstOrCreate(
                ['business_id' => $businessId, 'document_type' => $type],
                ['prefix' => self::TYPES[$type][0], 'next_number' => 1, 'padding' => 6],
            );
        }
    }

    /** Highest number already used in the series' own format (to stop the owner going backwards). */
    public function highestUsed(NumberSeries $series): int
    {
        [, , $table, $column] = self::TYPES[$series->document_type];
        if (! $table) {
            return 0;
        }

        return (int) DB::table($table)->where('business_id', $series->business_id)
            ->where($column, 'like', addcslashes($series->prefix, '%_').'%')
            ->pluck($column)
            ->map(fn ($n) => substr($n, strlen($series->prefix)))
            ->filter(fn ($s) => ctype_digit($s) && strlen($s) <= 12)
            ->map(fn ($s) => (int) $s)
            ->max();
    }

    private function seriesForUpdate(int $businessId, string $type): NumberSeries
    {
        $series = NumberSeries::query()->where('business_id', $businessId)->where('document_type', $type)->lockForUpdate()->first();
        if ($series) {
            return $series;
        }

        NumberSeries::query()->insertOrIgnore([
            'business_id' => $businessId, 'document_type' => $type, 'prefix' => self::TYPES[$type][0],
            'next_number' => 1, 'padding' => 6, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return NumberSeries::query()->where('business_id', $businessId)->where('document_type', $type)->lockForUpdate()->firstOrFail();
    }

    private function assertType(string $type): void
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException("Unknown document type [{$type}].");
        }
    }
}
