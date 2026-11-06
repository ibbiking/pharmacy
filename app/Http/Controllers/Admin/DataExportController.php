<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExportBusinessData;
use App\Models\BusinessExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/** Roadmap 13d: Settings → Data export (owners only). */
class DataExportController extends Controller
{
    private function businessId(): int
    {
        return (int) (session('impersonate_business_id') ?? session('business_id'));
    }

    private function ensureOwner(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->hasRole('super-admin') || $user->isOwnerOf($this->businessId()), 403, 'Only an owner can export the business data.');
    }

    public function index(Request $request)
    {
        $this->ensureOwner($request);
        $exports = BusinessExport::query()->with('requester:id,name')->latest('id')->paginate(10)
            ->through(fn (BusinessExport $e) => [
                'id' => $e->id,
                'status' => $e->status,
                'requested_by' => $e->requester?->name,
                'created_at' => $e->created_at?->format('d M Y, H:i'),
                'size' => $e->size,
                'tables' => $e->tables,
                'rows' => $e->rows,
                'error' => $e->status === 'failed' ? 'The export failed. Try again; if it keeps failing, contact support.' : null,
                'expires_at' => $e->expires_at?->format('d M Y'),
                'downloadable' => $e->downloadable(),
            ]);

        return Inertia::render('Settings/DataExport', [
            'exports' => $exports,
            'tableCount' => fn () => count(ExportBusinessData::tables()) + 2,
            'busy' => BusinessExport::query()->whereIn('status', ['pending', 'running'])->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureOwner($request);
        if (BusinessExport::query()->whereIn('status', ['pending', 'running'])->where('created_at', '>', now()->subHour())->exists()) {
            return back()->with('error', 'An export is already being prepared. It appears below when it is ready.');
        }
        $export = BusinessExport::create(['requested_by' => $request->user()->id, 'status' => 'pending']);
        ExportBusinessData::dispatch($export->id);

        return back()->with('success', 'Export started. Refresh in a minute to download it.');
    }

    public function download(Request $request, int $export)
    {
        $this->ensureOwner($request);
        $row = BusinessExport::query()->findOrFail($export); // scoped to this business
        abort_unless($row->downloadable() && Storage::disk('local')->exists($row->file_path), 404);

        return Storage::disk('local')->download($row->file_path, 'business-data-'.$row->business_id.'-'.$row->created_at->format('Y-m-d').'.zip');
    }
}
