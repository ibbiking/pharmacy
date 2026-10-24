@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout" style="padding-top:0;">

    {{-- Top Nav --}}
    <div class="gs-nav" style="max-width:600px;">
        <div class="gs-nav-brand">🎭 Gossip Slip</div>
        <div style="display:flex;gap:8px;align-items:center;">
            <span class="gs-text-muted" style="font-size:0.8rem;">{{ $admin->display_name }}</span>
            <form action="{{ route('gs.admin.logout') }}" method="POST" style="margin:0;">
                @csrf
                <button class="gs-btn gs-btn-ghost gs-btn-sm gs-btn-inline" type="submit">Logout 👋</button>
            </form>
        </div>
    </div>

    {{-- Welcome Header --}}
    <div class="gs-full-card gs-mb-16">
        <div style="text-align:center; padding:8px 0 20px;">
            <div style="font-size:2.5rem;">👑</div>
            <h1 class="gs-title">Marhaba, {{ $admin->display_name }}!</h1>
            <p class="gs-subtitle">Aaj kiska raaz bahar aayega? 😏</p>
        </div>

        <a href="{{ route('gs.admin.games.create') }}" class="gs-btn gs-btn-primary">
            🎮 Naya Game Banao!
        </a>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="gs-alert gs-alert-success" style="max-width:600px;width:100%;">✅ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="gs-alert gs-alert-error" style="max-width:600px;width:100%;">⚠️ {{ session('error') }}</div>
    @endif

    {{-- Active / Lobby Games --}}
    @php
        $activeGames = $games->whereIn('status', ['lobby','active']);
        $doneGames   = $games->where('status', 'completed');
    @endphp

    @if($activeGames->count())
    <div class="gs-full-card gs-mb-16">
        <div class="gs-section-title">🟢 Chal Rahe Games</div>

        @foreach($activeGames as $g)
        <div class="gs-game-card">
            <div class="gs-game-code">{{ $g->room_code }}</div>
            <div class="gs-game-meta">
                <div style="font-weight:700;">
                    @if($g->status === 'lobby')
                        <span class="gs-badge gs-badge-lobby">Lobby</span>
                    @else
                        <span class="gs-badge gs-badge-active">Active</span>
                    @endif
                </div>
                <div class="gs-game-meta-row">
                    👥 {{ $g->players_count }} khiladi &nbsp;·&nbsp;
                    🔄 {{ $g->rounds_count }} round &nbsp;·&nbsp;
                    🕐 {{ $g->created_at->diffForHumans() }}
                </div>
            </div>
            <a href="{{ route('gs.admin.games.lobby', $g->room_code) }}"
               class="gs-btn gs-btn-primary gs-btn-sm gs-btn-inline">
                Khelo! 🎯
            </a>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Completed Games --}}
    @if($doneGames->count())
    <div class="gs-full-card gs-mb-16">
        <div class="gs-section-title">🏁 Purane Games</div>

        @foreach($doneGames as $g)
        <div class="gs-game-card">
            <div class="gs-game-code" style="color:var(--gs-muted);">{{ $g->room_code }}</div>
            <div class="gs-game-meta">
                <div><span class="gs-badge gs-badge-completed">Khatam</span></div>
                <div class="gs-game-meta-row">
                    👥 {{ $g->players_count }} &nbsp;·&nbsp; 🔄 {{ $g->rounds_count }} rounds &nbsp;·&nbsp; {{ $g->created_at->format('d M') }}
                </div>
            </div>
            <a href="{{ route('gs.admin.history.show', $g->room_code) }}"
               class="gs-btn gs-btn-ghost gs-btn-sm gs-btn-inline">
                History 📜
            </a>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Empty State --}}
    @if($games->isEmpty())
    <div class="gs-full-card" style="text-align:center; padding:40px 24px;">
        <div style="font-size:4rem; margin-bottom:16px;">🎭</div>
        <h2 style="font-size:1.3rem; font-weight:800; margin-bottom:8px;">Koi game nahi abhi tak!</h2>
        <p class="gs-text-muted gs-mb-16">Pehla game chalao aur dosto ko hasao 😄</p>
        <a href="{{ route('gs.admin.games.create') }}" class="gs-btn gs-btn-primary gs-btn-inline" style="width:auto;padding:14px 28px;">
            🚀 Shuru Karo!
        </a>
    </div>
    @endif

    {{-- History Link --}}
    @if($games->isNotEmpty())
    <div class="gs-text-center gs-mt-8 gs-mb-16">
        <a href="{{ route('gs.admin.history') }}" class="gs-text-muted" style="text-decoration:none; font-weight:600;">
            📜 Puri history dekho
        </a>
    </div>
    @endif

</div>
@endsection
