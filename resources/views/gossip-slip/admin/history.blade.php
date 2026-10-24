@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout">

    <div class="gs-nav" style="max-width:600px;">
        <a href="{{ route('gs.admin.dashboard') }}" class="gs-nav-brand" style="text-decoration:none;">← Dashboard</a>
        <div class="gs-text-muted">📜 History</div>
    </div>

    <div class="gs-full-card">
        <div class="gs-text-center gs-mb-16">
            <div style="font-size:2.5rem;">📜</div>
            <h1 class="gs-title">Game History</h1>
            <p class="gs-subtitle">{{ $admin->display_name }} ke saare gossip sessions</p>
        </div>

        @if($games->isEmpty())
            <div style="text-align:center; padding:40px;">
                <div style="font-size:3.5rem; margin-bottom:16px;">🕵️</div>
                <h3 style="font-weight:800; margin-bottom:8px;">Koi purana game nahi mila!</h3>
                <p class="gs-text-muted gs-mb-16">Pehle koi game khelo, phir history aayegi 😄</p>
                <a href="{{ route('gs.admin.games.create') }}"
                   class="gs-btn gs-btn-primary gs-btn-inline" style="padding:12px 24px;">
                    🎮 Game Banao
                </a>
            </div>
        @else
            @foreach($games as $g)
            <div class="gs-game-card">
                <div class="gs-game-code" style="{{ $g->status === 'completed' ? 'color:var(--gs-muted)' : '' }}">
                    {{ $g->room_code }}
                </div>
                <div class="gs-game-meta">
                    <div>
                        @if($g->status === 'completed')
                            <span class="gs-badge gs-badge-completed">Khatam</span>
                        @elseif($g->status === 'active')
                            <span class="gs-badge gs-badge-active">Active</span>
                        @else
                            <span class="gs-badge gs-badge-lobby">Lobby</span>
                        @endif
                    </div>
                    <div class="gs-game-meta-row">
                        👥 {{ $g->players_count }} khiladi &nbsp;·&nbsp;
                        🔄 {{ $g->rounds_count }} rounds &nbsp;·&nbsp;
                        📅 {{ $g->created_at->format('d M Y') }}
                    </div>
                </div>
                <a href="{{ route('gs.admin.history.show', $g->room_code) }}"
                   class="gs-btn gs-btn-secondary gs-btn-sm gs-btn-inline">
                    Dekho 👁️
                </a>
            </div>
            @endforeach
        @endif
    </div>

</div>
@endsection
