<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\NumberSeries;
use App\Services\Numbering\NumberSeriesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Settings → Numbering (Roadmap 4g): prefix, next number and padding per document type. Owner only. */
class NumberingController extends Controller
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    public function index(Request $request)
    {
        $business = $this->ownerBusiness($request);
        $this->numbers->ensureDefaults($business->id);

        $series = NumberSeries::query()->where('business_id', $business->id)->whereIn('document_type', NumberSeriesService::ACTIVE)->get()->keyBy('document_type');

        return Inertia::render('Settings/Numbering', [
            'series' => collect(NumberSeriesService::ACTIVE)->map(fn ($type) => [
                'type' => $type,
                'label' => NumberSeriesService::TYPES[$type][1],
                'prefix' => $series[$type]->prefix,
                'next_number' => $series[$type]->next_number,
                'padding' => $series[$type]->padding,
                'preview' => $series[$type]->format($series[$type]->next_number),
                'highest_used' => $this->numbers->highestUsed($series[$type]),
            ])->values(),
            'fbr' => $business->isFbrInvoicing(),
        ]);
    }

    public function update(Request $request, string $type)
    {
        $business = $this->ownerBusiness($request);
        abort_unless(in_array($type, NumberSeriesService::ACTIVE, true), 404);

        $data = $request->validate([
            'prefix' => ['present', 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-\/_.]*$/'],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'padding' => ['required', 'integer', 'between:1,12'],
        ], ['prefix.regex' => 'Use letters, numbers and - / _ . only.']);

        $series = NumberSeries::query()->where('business_id', $business->id)->where('document_type', $type)->firstOrFail();
        $series->fill(['prefix' => (string) ($data['prefix'] ?? ''), 'padding' => $data['padding']]);

        // Never go back below a number already used with this prefix (that would create duplicates).
        $highest = $this->numbers->highestUsed($series);
        if ($data['next_number'] <= $highest) {
            throw ValidationException::withMessages(['next_number' => "Must be higher than {$highest}, the last number already used with this prefix."]);
        }

        $series->next_number = $data['next_number'];
        $series->save();

        return back()->with('success', NumberSeriesService::TYPES[$type][1].' numbering saved. Next: '.$series->format($series->next_number));
    }

    private function ownerBusiness(Request $request): Business
    {
        $business = Business::findOrFail((int) (session('impersonate_business_id') ?? session('business_id')));
        abort_unless($request->user()->can('core.settings.manage'), 403);

        return $business;
    }
}
