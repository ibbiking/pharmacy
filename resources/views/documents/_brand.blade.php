{{-- Business header for printed documents: logo, or a badge with the initials on the brand colour. --}}
@php
    $logoUrl = $business?->logo && \Illuminate\Support\Facades\Storage::disk('uploads')->exists($business->logo)
        ? \Illuminate\Support\Facades\Storage::disk('uploads')->url($business->logo) : null;
    $initials = collect(preg_split('/\s+/', trim($business?->name ?? 'B')))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<div class="brand">
    @if ($logoUrl)
        <img src="{{ $logoUrl }}" alt="" class="logo">
    @else
        <span class="badge-logo" style="background: {{ $business?->brand_color ?: '#6d4aff' }}">{{ $initials }}</span>
    @endif
    <div>
        <div class="biz-name">{{ $business?->name }}</div>
        @if ($business?->address)<div class="muted">{{ $business->address }}</div>@endif
        @if ($business?->phone)<div class="muted">{{ $business->phone }}</div>@endif
    </div>
</div>
