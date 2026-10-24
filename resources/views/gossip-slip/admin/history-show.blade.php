@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout">

    <div class="gs-nav" style="max-width:700px;">
        <a href="{{ route('gs.admin.history') }}" class="gs-nav-brand" style="text-decoration:none;">← History</a>
        <span class="gs-badge gs-badge-completed">{{ $game->room_code }}</span>
    </div>

    <div class="gs-full-card gs-mb-16" style="max-width:700px;width:100%;text-align:center;">
        <div style="font-size:2rem;">📜</div>
        <h1 class="gs-title">Room: {{ $game->room_code }}</h1>
        <p class="gs-subtitle">
            👥 {{ $game->players->count() }} khiladi &nbsp;·&nbsp;
            🔄 {{ $game->rounds->count() }} rounds &nbsp;·&nbsp;
            📅 {{ $game->created_at->format('d M Y') }}
        </p>

        <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap; margin-top:12px;">
            @foreach($game->players as $p)
                <span class="gs-badge" style="background:#F0F0F5; color:var(--gs-dark);">
                    {{ $p->name }}{{ $p->is_admin ? ' 👑' : '' }}
                </span>
            @endforeach
        </div>
    </div>

    @forelse($game->rounds->sortBy('round_number') as $round)
    <div class="gs-full-card gs-mb-16" style="max-width:700px;width:100%;">

        {{-- Round Header --}}
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
            <div>
                <div style="font-size:1.1rem; font-weight:800;">
                    🔄 Round {{ $round->round_number }}
                </div>
                <div class="gs-text-muted" style="font-size:0.8rem;">
                    {{ $round->gender_mode === 'male' ? '🕺 Mard Round' : '💃 Aurat Round' }} &nbsp;·&nbsp;
                    <span class="gs-badge gs-badge-completed" style="font-size:0.7rem;">{{ ucfirst($round->status) }}</span>
                </div>
            </div>
        </div>

        {{-- Chit Tiles --}}
        <div class="gs-chits-grid" style="grid-template-columns:1fr;">
            @foreach($round->chits as $i => $chit)
            @php
                $sentence = $chit->getRevealSentence();
                $btnId    = 'tts-btn-' . $chit->id;
            @endphp
            <div class="gs-chit-tile" style="cursor:default;">
                <div class="gs-chit-origin">
                    📌 Parchi #{{ $i + 1 }}
                </div>

                @if($chit->isComplete())
                    {{-- The Gossip Sentence --}}
                    <div class="gs-reveal-sentence" style="font-size:1.1rem; margin:10px 0;">
                        <span class="gs-reveal-word gs-word-name">{{ $chit->name_val }}</span>
                        &mdash;
                        <span class="gs-reveal-word gs-word-action">{{ $chit->action_val }}</span>
                        <span style="color:var(--gs-muted); font-weight:600;">
                            {{ $round->gender_mode === 'male' ? ' kar raha hai' : ' kar rahi hai' }}
                        </span>
                        &mdash;
                        <span class="gs-reveal-word gs-word-location">{{ $chit->location_val }}</span>
                        <span style="color:var(--gs-muted);"> mein!</span>
                    </div>

                    {{-- TTS Button --}}
                    <button class="gs-tts-btn" id="{{ $btnId }}"
                            onclick="historySpeak('{{ addslashes($sentence) }}', this)"
                            style="font-size:0.85rem; padding:8px 16px;">
                        🔊 Sunao!
                    </button>

                    {{-- Credits --}}
                    <div class="gs-reveal-credits">
                        ✍️ <strong>Naam</strong>: {{ optional($chit->step1Player)->name ?? '?' }}
                        &nbsp;·&nbsp;
                        ✍️ <strong>Harkat</strong>: {{ optional($chit->step2Player)->name ?? '?' }}
                        &nbsp;·&nbsp;
                        ✍️ <strong>Jagha</strong>: {{ optional($chit->step3Player)->name ?? '?' }}
                    </div>
                @else
                    <div class="gs-text-muted" style="padding:12px 0;">
                        ⚠️ Ye parchi adhoori reh gayi (koi game chhod gaya) 😔
                    </div>
                @endif
            </div>
            @endforeach

            @if($round->chits->isEmpty())
                <div class="gs-text-muted gs-text-center" style="padding:20px;">
                    Is round mein koi chit nahi bani.
                </div>
            @endif
        </div>

    </div>
    @empty
        <div class="gs-full-card gs-text-center" style="max-width:700px;width:100%;padding:40px;">
            <div style="font-size:3rem; margin-bottom:16px;">🎭</div>
            <h3 style="font-weight:800;">Koi round nahi chala tha!</h3>
        </div>
    @endforelse

</div>
@endsection
