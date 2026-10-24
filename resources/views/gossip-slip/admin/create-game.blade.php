@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout">

    {{-- Back Nav --}}
    <div class="gs-nav" style="max-width:600px;">
        <a href="{{ route('gs.admin.dashboard') }}" class="gs-nav-brand" style="text-decoration:none;">
            ← Dashboard
        </a>
        <div class="gs-text-muted" style="font-size:0.85rem;">Naya Game</div>
    </div>

    {{-- Card with Alpine.js --}}
    <div class="gs-full-card" x-data="gossipCreateGame()">

        <div class="gs-text-center gs-mb-16">
            <div style="font-size:2.5rem;">🎮</div>
            <h1 class="gs-title">Naya Game Banao</h1>
            <p class="gs-subtitle">Khiladi add karo aur mazaa shuru karo!</p>
        </div>

        <form action="{{ route('gs.admin.games.store') }}" method="POST">
            @csrf

            {{-- Player Count --}}
            <div class="gs-form-group">
                <label class="gs-label">👥 Kitne Khiladi? (3–15)</label>
                <div style="display:flex; gap:12px; align-items:center;">
                    <input class="gs-input" type="number" min="2" max="15"
                           x-model.number="playerCount"
                           @change="updatePlayerCount($event.target.value)"
                           style="max-width:100px; text-align:center; font-size:1.2rem; font-weight:700;"
                           value="2">
                    <div class="gs-text-muted" style="font-size:0.85rem;">
                        2 bhi khelenge — aur agar admin bhi khail raha hai to 3 ho jaate hain! 🎲
                    </div>
                </div>
            </div>

            {{-- Player Name Inputs --}}
            <div class="gs-form-group">
                <label class="gs-label">✍️ Khiladi ke Naam</label>
                <div style="display:grid; grid-template-columns: repeat(2,1fr); gap:10px;">
                    <template x-for="(name, index) in players" :key="index">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span x-text="index + 1" style="font-weight:700; color:var(--gs-muted); min-width:20px; text-align:right;"></span>
                            <input class="gs-input"
                                   :name="'player_names[' + index + ']'"
                                   x-model="players[index]"
                                   :placeholder="'Khiladi ' + (index+1)"
                                   style="padding:10px 12px; font-size:0.9rem;"
                                   required>
                        </div>
                    </template>
                </div>
            </div>

            <div class="gs-divider"></div>

            {{-- Admin Plays Toggle --}}
            <div class="gs-form-group">
                <label style="display:flex; align-items:center; gap:12px; cursor:pointer; padding:14px; background:#F8F8FF; border-radius:var(--gs-radius); border:2px solid #E8E8F0;">
                    <input type="checkbox" name="admin_plays" value="1"
                           x-model="adminPlays"
                           style="width:20px; height:20px; accent-color:var(--gs-primary);">
                    <div>
                        <div style="font-weight:700;">🎮 Kya aap bhi khailenge?</div>
                        <div class="gs-text-muted" style="font-size:0.8rem;">Haan main bhi list mein hoon!</div>
                    </div>
                </label>
            </div>

            {{-- Admin Player Name (if playing) --}}
            <div class="gs-form-group" x-show="adminPlays" x-transition style="margin-top:8px;">
                <label class="gs-label">🧑 Game mein aapka naam kya hoga?</label>
                <input class="gs-input" type="text" name="admin_player_name"
                       x-model="adminPlayerName"
                       placeholder="Jaise: 'Ustaad G' ya 'Khiladi No.1'"
                       :required="adminPlays">
                <small class="gs-text-muted">Doston ko pata nahi chalega ke aap admin hain 😏</small>
            </div>

            <div class="gs-divider"></div>

            {{-- Submit --}}
            <button class="gs-btn gs-btn-primary" type="submit">
                🚀 Game Shuru Karo!
            </button>

        </form>

    </div>

</div>
@endsection
