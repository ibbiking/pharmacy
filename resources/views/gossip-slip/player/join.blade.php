@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout" x-data="{ selectedId: null }" style="padding-top:24px;">

    {{-- Header --}}
    <div class="gs-full-card gs-mb-16" style="max-width:430px;text-align:center;">
        <div style="font-size:3rem; animation: bounce 2s ease-in-out infinite; display:block; margin-bottom:8px;">🎭</div>
        <h1 class="gs-title" style="font-size:1.6rem;">Gossip Slip!</h1>
        <p class="gs-subtitle">Apna naam chunno aur khel mein shaamil ho!</p>

        <div class="gs-alert gs-alert-info" style="text-align:left; font-size:0.85rem;">
            📌 Room Code: <strong style="letter-spacing:3px; font-size:1rem;">{{ $game->room_code }}</strong>
        </div>

        @if(session('error'))
            <div class="gs-alert gs-alert-error">⚠️ {{ session('error') }}</div>
        @endif
    </div>

    {{-- Player Selection --}}
    <div class="gs-full-card" style="max-width:430px;">
        <div class="gs-section-title" style="justify-content:center; margin-bottom:16px;">
            👇 Apna Naam Chunno
        </div>

        <form action="{{ url('/gossip-slip/join/' . $game->room_code) }}" method="POST">
            @csrf
            <input type="hidden" name="player_id" :value="selectedId" x-ref="playerIdInput">

            {{-- Name Cards Grid --}}
            <div class="gs-name-grid">
                @php
                    $emojis = ['🧑','👩','🧔','👱','🙋','🙆','🤵','👳','🧕','👸','🤴','🦸','🦹','🧙','🧝'];
                    $ei = 0;
                @endphp
                @foreach($players as $player)
                @php $emoji = $emojis[$ei % count($emojis)]; $ei++; @endphp
                <div class="gs-player-name-card"
                     :class="{ 'selected': selectedId === {{ $player->id }} }"
                     @click="selectedId = {{ $player->id }}">
                    <span class="card-emoji">{{ $emoji }}</span>
                    <span>{{ $player->name }}</span>
                </div>
                @endforeach
            </div>

            {{-- Submit --}}
            <div x-show="selectedId" x-transition style="margin-top:16px;">
                <button class="gs-btn gs-btn-primary" type="submit" @click="$refs.playerIdInput.value = selectedId">
                    🚀 Aagay Badho!
                </button>
                <p class="gs-text-muted gs-text-center" style="margin-top:10px; font-size:0.8rem;">
                    Agar pehle join kiya tha toh wahan se resume ho jayega ✅
                </p>
            </div>

            <div x-show="!selectedId" class="gs-text-center gs-mt-16">
                <p class="gs-text-muted" style="font-size:0.9rem;">👆 Naam chunne ke baad "Aagay Badho" ayega!</p>
            </div>

        </form>

    </div>

    <div class="gs-text-center gs-mt-16" style="max-width:430px;">
        <p class="gs-text-muted" style="font-size:0.78rem;">
            🔒 Tumhara naam sirf tumhare liye hai. Koi dekhega nahi ke kisne kya likha!
        </p>
    </div>

</div>
@endsection
