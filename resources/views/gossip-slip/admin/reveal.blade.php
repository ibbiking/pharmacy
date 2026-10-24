@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout"
     x-data="gossipReveal('{{ $code }}')"
     x-init="">

    {{-- Nav --}}
    <div class="gs-nav" style="max-width:600px;">
        <a href="{{ route('gs.admin.games.lobby', $code) }}" class="gs-nav-brand" style="text-decoration:none;">
            ← Lobby
        </a>
        <div>
            <span class="gs-badge gs-badge-reveal">🎭 Round {{ $round->round_number }}</span>
        </div>
    </div>

    <div class="gs-full-card gs-mb-16" style="text-align:center;">
        <div style="font-size:2.5rem; margin-bottom:8px;">🎭</div>
        <h1 class="gs-title">Reveal Gallery!</h1>
        <p class="gs-subtitle">
            {{ $chits->where('is_revealed', true)->count() }}/{{ $chits->count() }} parchiyan reveal ho chuki hain
            · {{ $round->gender_mode === 'male' ? '🕺 Mard' : '💃 Aurat' }} round
        </p>

        <div class="gs-progress-bar-wrap" style="margin:12px 0;">
            @php $pct = $chits->count() ? round($chits->where('is_revealed', true)->count() / $chits->count() * 100) : 0; @endphp
            <div class="gs-progress-bar" style="width:{{ $pct }}%"></div>
        </div>
    </div>

    {{-- Chit Grid --}}
    <div class="gs-chits-grid" style="max-width:600px;width:100%;">
        @foreach($chits as $i => $chit)
        <div id="chit-tile-{{ $chit->id }}"
             class="gs-chit-tile {{ $chit->is_revealed ? 'revealed' : '' }}"
             @click="openChit({{ $chit->id }})">
            <div class="gs-chit-origin">📌 Parchi #{{ $i + 1 }}</div>
            <div class="gs-chit-number">
                {{ $chit->is_revealed ? '📖' : '📜' }}
            </div>
            <div style="font-size:0.75rem; color:var(--gs-muted); margin-top:8px; font-weight:600;">
                @if($chit->is_revealed)
                    ✓ Revealed
                @else
                    Tap to reveal &amp; hear! 🔊
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Loading indicator --}}
    <div x-show="isLoading" class="gs-text-center gs-mt-16">
        <div class="gs-dots-loader">
            <span></span><span></span><span></span>
        </div>
    </div>

    {{-- Round Actions --}}
    <div class="gs-full-card gs-mt-16" style="max-width:600px;width:100%;text-align:center;">
        <form action="{{ route('gs.admin.games.finish-round', $code) }}" method="POST" style="display:inline;">
            @csrf
            <button class="gs-btn gs-btn-danger gs-btn-inline" style="padding:12px 24px;"
                    onclick="return confirm('Is round ko khatam karein? Naya round lobby se shuru hoga.')">
                🏁 Round Khatam Karo
            </button>
        </form>
        <a href="{{ route('gs.admin.games.lobby', $code) }}"
           class="gs-btn gs-btn-ghost gs-btn-inline gs-mt-8" style="padding:12px 24px;">
            ← Lobby Wapas
        </a>
    </div>

    {{-- ===================== REVEAL MODAL ===================== --}}
    <div class="gs-modal-overlay" x-show="showModal" x-transition:enter="fadeIn"
         @click.self="closeModal()" style="display:none;">

        <div class="gs-modal-card" x-show="showModal && !isLoading" x-transition>

            {{-- Loading --}}
            <div x-show="isLoading" style="text-align:center; padding:40px;">
                <div class="gs-dots-loader" style="justify-content:center;">
                    <span></span><span></span><span></span>
                </div>
                <p class="gs-text-muted gs-mt-8">Parchi khul rahi hai...</p>
            </div>

            {{-- Reveal Content --}}
            <div x-show="revealData && !isLoading">
                <div style="text-align:center; margin-bottom:16px;">
                    <span style="font-size:2.5rem;">📖</span>
                    <h2 style="font-weight:900; font-size:1.2rem; margin-top:8px; color:var(--gs-secondary);">
                        Gossip Revealed! 🎉
                    </h2>
                </div>

                {{-- The sentence --}}
                <div class="gs-reveal-sentence" x-show="revealData">
                    <span class="gs-reveal-word gs-word-name"    x-text="revealData?.name_val ?? '???'"></span>
                    &nbsp;—&nbsp;
                    <span class="gs-reveal-word gs-word-action"  x-text="revealData?.action_val ?? '???'"></span>
                    <span x-text="revealData?.gender_mode === 'male' ? ' kar raha hai' : ' kar rahi hai'"
                          style="font-weight:600; color:var(--gs-muted);"></span>
                    &nbsp;—&nbsp;
                    <span class="gs-reveal-word gs-word-location" x-text="revealData?.location_val ?? '???'"></span>
                    <span style="font-weight:600; color:var(--gs-muted);"> mein!</span>
                </div>

                {{-- TTS Button --}}
                <button class="gs-tts-btn" :class="{ 'tts-active': ttsActive }" @click="speakChit()"
                        style="margin:16px auto;">
                    <span x-text="ttsActive ? '🔇 Rok Do' : '🔊 Sunao!'"></span>
                </button>

                {{-- Credits --}}
                <div class="gs-reveal-credits" x-show="revealData">
                    <div>✍️ <strong>Naam</strong> likha: <span x-text="revealData?.step1_by ?? '?'"></span></div>
                    <div>✍️ <strong>Harkat</strong> likhi: <span x-text="revealData?.step2_by ?? '?'"></span></div>
                    <div>✍️ <strong>Jagha</strong> likhi: <span x-text="revealData?.step3_by ?? '?'"></span></div>
                </div>

                {{-- Close --}}
                <button class="gs-btn gs-btn-ghost gs-mt-16" @click="closeModal()">
                    ✕ Band Karo
                </button>
            </div>

        </div>
    </div>
    {{-- ================= END REVEAL MODAL ================= --}}

</div>
@endsection
