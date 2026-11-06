<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Super admin: whole-platform backups (spatie/laravel-backup), Roadmap 5.11b.
 * SA-2: a backup holds EVERY business's data, so this is super-admin only (route group), and download / delete only
 * reach "<backup name>/*.zip" on a configured backup disk — the old screen took any disk and any file name.
 */
class BackupController extends Controller
{
    private function disks(): array
    {
        return array_values(config('backup.backup.destination.disks', []));
    }

    private function folder(): string
    {
        return (string) config('backup.backup.name');
    }

    /** A backup zip on a backup disk, or 404. Returns [disk, path]. */
    private function resolve(Request $request, ?string $fileName): array
    {
        $diskName = (string) $request->input('disk', $this->disks()[0] ?? '');
        $name = basename((string) ($fileName ?? $request->input('file_name', '')));
        abort_unless(in_array($diskName, $this->disks(), true) && preg_match('/^[\w.\-]+\.zip$/', $name), 404);
        $disk = Storage::disk($diskName);
        $path = $this->folder().'/'.$name;
        abort_unless($disk->exists($path), 404);

        return [$disk, $path];
    }

    public function index()
    {
        $backups = collect($this->disks())->flatMap(function (string $diskName) {
            $disk = Storage::disk($diskName);

            return collect($disk->files($this->folder()))
                ->filter(fn ($f) => str_ends_with($f, '.zip'))
                ->map(fn ($f) => [
                    'id' => $diskName.':'.basename($f),
                    'name' => basename($f),
                    'disk' => $diskName,
                    'size' => $disk->size($f),
                    'created_at' => date('d M Y, H:i', $disk->lastModified($f)),
                    'timestamp' => $disk->lastModified($f),
                    'downloadable' => $disk->getAdapter() instanceof LocalFilesystemAdapter,
                ]);
        })->sortByDesc('timestamp')->values();

        $page = (int) max(1, request()->integer('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator($backups->forPage($page, 20)->values(), $backups->count(), 20, $page, ['path' => route('backup.index')]);

        return Inertia::render('SuperAdmin/Backups', [
            'backups' => $paginator,
            'summary' => ['count' => $backups->count(), 'total_size' => $backups->sum('size'), 'latest' => $backups->first()['created_at'] ?? null, 'disks' => $this->disks()],
        ]);
    }

    public function create()
    {
        @set_time_limit(600);
        Log::info('Backup started from the super admin screen by user '.auth()->id());
        try {
            $code = Artisan::call('backup:run', ['--disable-notifications' => true]);
            $output = Artisan::output();
        } catch (\Throwable $e) {
            Log::error($e);

            return back()->with('error', 'The backup failed: '.$e->getMessage());
        }
        if ($code !== 0 || str_contains($output, 'Backup failed')) {
            Log::error('Backup failed: '.$output);

            return back()->with('error', 'The backup failed. Details are in the application log.');
        }

        return back()->with('success', 'Backup created.');
    }

    public function download(Request $request, ?string $file_name = null)
    {
        [$disk, $path] = $this->resolve($request, $file_name);
        abort_unless($disk->getAdapter() instanceof LocalFilesystemAdapter, 404);

        return response()->download($disk->path($path));
    }

    public function destroy(Request $request, ?string $file_name = null)
    {
        [$disk, $path] = $this->resolve($request, $file_name);
        $disk->delete($path);
        Log::info("Backup {$path} deleted by user ".auth()->id());

        return back()->with('success', 'Backup deleted.');
    }
}
