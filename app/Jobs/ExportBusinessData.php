<?php

namespace App\Jobs;

use App\Models\BusinessExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Roadmap 13d: "Export my data". One CSV per table that has a business_id column (only this business's rows, archived
 * rows included), plus the business itself and its members. Secret columns (passwords, tokens, API keys, Stripe ids)
 * are never exported. Written to storage/app/exports/<business>/<id>.zip and kept 7 days.
 */
class ExportBusinessData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    /** Transient or internal tables that mean nothing in an export. */
    private const SKIP = ['pos_cart_reservations', 'business_exports', 'generic_product_import_batches'];

    private const SECRET = '/password|token|secret|api_key|stripe_|remember/i';

    public function __construct(public int $exportId)
    {
    }

    /** Tables to export: every table with a business_id column (except other tools' bec_* tables). */
    public static function tables(): array
    {
        return collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn ($t) => str_starts_with($t, 'bec_') || in_array($t, self::SKIP, true))
            ->filter(fn ($t) => Schema::hasColumn($t, 'business_id'))
            ->sort()->values()->all();
    }

    public function handle(): void
    {
        $export = BusinessExport::withoutGlobalScopes()->find($this->exportId);
        if (! $export || $export->status === 'done') {
            return;
        }
        $export->update(['status' => 'running']);
        $businessId = (int) $export->business_id;
        $tmp = storage_path('app/exports/tmp-'.$export->id.'-'.bin2hex(random_bytes(4)));
        @mkdir($tmp, 0775, true);

        try {
            $rows = 0;
            $files = [];
            $write = function (string $name, $query) use ($tmp, &$rows, &$files) {
                $path = "{$tmp}/{$name}.csv";
                $fh = fopen($path, 'w');
                $header = null;
                $query->orderBy($query->from === 'users' ? 'users.id' : 'id')->chunk(1000, function ($chunk) use ($fh, &$header, &$rows) {
                    foreach ($chunk as $row) {
                        $row = array_filter((array) $row, fn ($k) => ! preg_match(self::SECRET, (string) $k), ARRAY_FILTER_USE_KEY);
                        if ($header === null) {
                            $header = array_keys($row);
                            fputcsv($fh, $header);
                        }
                        fputcsv($fh, array_map(fn ($v) => is_scalar($v) || $v === null ? $v : json_encode($v), array_values($row)));
                        $rows++;
                    }
                });
                if ($header === null) {
                    fputcsv($fh, ['(no rows)']);
                }
                fclose($fh);
                $files[] = $path;
            };

            $write('business', DB::table('businesses')->where('id', $businessId));
            $write('members', DB::table('users')->join('business_user', 'business_user.user_id', '=', 'users.id')
                ->where('business_user.business_id', $businessId)
                ->select('users.id', 'users.name', 'users.email', 'business_user.role', 'business_user.status', 'business_user.created_at as joined_at'));
            foreach (self::tables() as $table) {
                $write($table, DB::table($table)->where('business_id', $businessId));
            }

            file_put_contents("{$tmp}/README.txt", implode(PHP_EOL, [
                'Data export of business #'.$businessId.' created '.now()->toDateTimeString().'.',
                'One CSV file per table, only this business\'s rows (archived rows included: see the deleted_at column).',
                'Passwords, tokens, API keys and payment-provider ids are not included.',
                'Amounts are in the business currency. Dates are in '.config('app.timezone').'.',
            ]));
            $files[] = "{$tmp}/README.txt";

            $relative = "exports/{$businessId}/export-{$export->id}-".now()->format('Ymd-His').'.zip';
            $zipPath = Storage::disk('local')->path($relative);
            @mkdir(dirname($zipPath), 0775, true);
            $zip = new ZipArchive;
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($files as $f) {
                $zip->addFile($f, basename($f));
            }
            $zip->close();

            $export->update([
                'status' => 'done', 'file_path' => $relative, 'size' => filesize($zipPath), 'tables' => count($files) - 1,
                'rows' => $rows, 'finished_at' => now(), 'expires_at' => now()->addDays(7),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $export->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 480), 'finished_at' => now()]);
        } finally {
            foreach (glob("{$tmp}/*") ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmp);
        }
    }

    public function failed(\Throwable $e): void
    {
        BusinessExport::withoutGlobalScopes()->whereKey($this->exportId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 480)]);
    }
}
