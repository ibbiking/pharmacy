@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout" style="padding-top:0;"
     x-data="gossipAdminLobby('{{ $game->room_code }}')"
     x-init="init()"
     @beforeunload.window="stopPolling()">

    {{-- Nav --}}
    <div class="gs-nav" style="max-width:700px;">
        <a href="{{ route('gs.admin.dashboard') }}" class="gs-nav-brand" style="text-decoration:none;">← Dashboard</a>
        <div style="display:flex;gap:8px;align-items:center;">
            <span class="gs-badge"
                  :class="{ 'gs-badge-lobby': gameStatus==='lobby', 'gs-badge-active': gameStatus==='active', 'gs-badge-completed': gameStatus==='completed' }"
                  x-text="gameStatus==='lobby' ? '🏠 Lobby' : (gameStatus==='active' ? '🎮 Active' : '🏁 Khatam')">
            </span>
            <a href="{{ route('gs.admin.history') }}" class="gs-btn gs-btn-ghost gs-btn-sm gs-btn-inline">📜</a>
        </div>
    </div>

    {{-- Room Header --}}
    <div class="gs-room-header" style="max-width:700px;width:100%;">
        <div style="font-size:0.85rem;color:rgba(255,255,255,0.7);margin-bottom:4px;font-weight:600;">ROOM CODE</div>
        <div class="gs-room-code">{{ $game->room_code }}</div>
        <div class="gs-join-url" @click="copyJoinUrl('{{ $joinUrl }}')" title="Tap to copy">
            📋 {{ $joinUrl }}
        </div>
        <div style="margin-top:10px;font-size:0.85rem;color:rgba(255,255,255,0.8);">
            <span x-text="onlineCount()"></span>/{{ $game->players->count() }} online
            &nbsp;·&nbsp; Round <span x-text="roundNumber ?? '—'"></span>
        </div>
    </div>

    {{-- Gender Mode Banner (live, always visible when round active) --}}
    <div style="max-width:700px;width:100%;margin-bottom:4px;"
         x-show="genderLabel && roundStatus" x-transition>
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;padding:12px 20px;border-radius:50px;font-weight:800;font-size:1rem;border:2px solid;"
             :style="genderBg()">
            <span x-text="genderLabel"></span>
            <span style="font-size:0.75rem;opacity:0.7;">· Round <span x-text="roundNumber"></span></span>
        </div>
    </div>

    {{-- Status / Error Messages --}}
    @if(session('success'))
        <div class="gs-alert gs-alert-success" style="max-width:700px;width:100%;">✅ {{ session('success') }}</div>
    @endif
    <div class="gs-alert gs-alert-success" style="max-width:700px;width:100%;"
         x-show="statusMsg" x-text="statusMsg" x-cloak></div>
    <div class="gs-alert gs-alert-error" style="max-width:700px;width:100%;"
         x-show="errorMsg" x-text="errorMsg" x-cloak></div>

    <div style="max-width:700px;width:100%;display:grid;gap:16px;">

        {{-- ================================================================
             NEXT ROUND CARD — auto-shown when round hits "reveal" (all done)
             ================================================================ --}}
        <div x-show="roundStatus === 'reveal'" x-transition x-cloak
             style="background:linear-gradient(135deg,#1A1A2E,#7B2D8B);border-radius:20px;padding:24px;color:white;text-align:center;">

            <div style="font-size:2.5rem;margin-bottom:8px;">🎉</div>
            <div style="font-size:1.3rem;font-weight:900;margin-bottom:4px;">Sab ne likh diya!</div>
            <div style="font-size:0.9rem;opacity:0.8;margin-bottom:20px;">
                Round <span x-text="roundNumber"></span> complete — ab reveal karo ya naya round shuru karo!
            </div>

            {{-- Reveal Button --}}
            <a :href="'/gossip-slip/admin/games/{{ $game->room_code }}/reveal'"
               style="display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,#FFD93D,#FF8C42);color:#1A1A2E;font-weight:900;font-size:1.1rem;padding:16px 24px;border-radius:50px;text-decoration:none;margin-bottom:12px;box-shadow:0 6px 20px rgba(255,217,61,0.4);transition:all 0.2s ease;"
               onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
                🎭 Reveal Gallery Kholo!
            </a>

            <div style="height:1px;background:rgba(255,255,255,0.15);margin:16px 0;"></div>

            {{-- Next Round with auto-flipped gender --}}
            <div style="font-size:0.85rem;opacity:0.7;margin-bottom:12px;">Agla Round:</div>

            <div style="display:flex;background:rgba(255,255,255,0.12);border-radius:50px;padding:4px;gap:4px;margin-bottom:16px;">
                <button type="button"
                        style="flex:1;padding:10px 16px;border-radius:50px;border:none;font-weight:700;font-size:0.9rem;cursor:pointer;transition:all 0.2s ease;"
                        :style="selectedGender==='male' ? 'background:white;color:#1E40AF;' : 'background:transparent;color:rgba(255,255,255,0.7);'"
                        @click="selectedGender='male'">
                    🕺 Mard
                </button>
                <button type="button"
                        style="flex:1;padding:10px 16px;border-radius:50px;border:none;font-weight:700;font-size:0.9rem;cursor:pointer;transition:all 0.2s ease;"
                        :style="selectedGender==='female' ? 'background:white;color:#9D174D;' : 'background:transparent;color:rgba(255,255,255,0.7);'"
                        @click="selectedGender='female'">
                    💃 Aurat
                </button>
            </div>

            {{-- Auto-selected indicator --}}
            <div style="font-size:0.78rem;opacity:0.65;margin-bottom:14px;">
                ⚡ Auto-selected: <strong x-text="selectedGender==='male' ? '🕺 Mard Round' : '💃 Aurat Round'"></strong>
                (pichle round se ulta)
            </div>

            <button class="gs-btn" @click="startRound()" :disabled="startingRound"
                    style="background:linear-gradient(135deg,var(--gs-success),#08EDB5);color:white;font-size:1rem;font-weight:900;box-shadow:0 6px 20px rgba(6,214,160,0.4);">
                <span x-show="!startingRound">🚀 Agla Round Shuru Karo!</span>
                <span x-show="startingRound">Round shuru ho raha hai...</span>
            </button>
        </div>

        {{-- ================================================================
             PLAYER STATUS GRID
             ================================================================ --}}
        <div class="gs-full-card">
            <div class="gs-section-title">👥 Khiladi Status</div>

            <div class="gs-player-grid">
                <template x-for="player in players" :key="player.id">
                    <div class="gs-player-row">
                        {{-- Step dots --}}
                        <div class="gs-step-badges">
                            <template x-for="step in [1,2,3]" :key="step">
                                <div class="gs-step-dot"
                                     :class="{
                                         done:    player['step'+step+'_done'],
                                         pending: !player['step'+step+'_done'] && isRoundActive()
                                     }"
                                     :title="'Step ' + step">
                                    <span x-text="player['step'+step+'_done'] ? '✓' : step"></span>
                                </div>
                            </template>
                        </div>

                        {{-- Name --}}
                        <div class="gs-player-name-label">
                            <span x-text="player.name"></span>
                            <span x-show="player.is_admin" class="gs-badge gs-badge-active"
                                  style="font-size:0.6rem;padding:2px 6px;margin-left:4px;">Admin</span>
                        </div>

                        {{-- Online dot --}}
                        <div class="gs-online-dot" :class="{ online: player.is_online }"
                             :title="player.is_online ? 'Online' : 'Offline'"></div>

                        {{-- Remove button (only when round NOT active) --}}
                        <button x-show="!isRoundActive() && !player.is_admin"
                                @click="removePlayer(player.id, player.name)"
                                title="Hatao"
                                style="background:none;border:none;cursor:pointer;font-size:1rem;padding:2px 6px;color:var(--gs-danger);opacity:0.6;transition:opacity 0.2s;"
                                onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'">
                            🗑️
                        </button>
                    </div>
                </template>
                <template x-if="players.length === 0">
                    <div class="gs-text-center gs-text-muted" style="padding:20px;">Koi khiladi nahi... 👻</div>
                </template>
            </div>

            {{-- Add Player (lobby only) --}}
            <div x-show="gameStatus === 'lobby'" x-transition style="margin-top:12px;">
                <div style="display:flex;gap:8px;">
                    <input class="gs-input" type="text" x-model="newPlayerName"
                           placeholder="Naye khiladi ka naam..."
                           style="font-size:0.9rem;"
                           @keyup.enter="addPlayer()">
                    <button class="gs-btn gs-btn-success gs-btn-sm gs-btn-inline"
                            @click="addPlayer()" :disabled="addingPlayer">➕</button>
                </div>
            </div>
        </div>

        {{-- ================================================================
             ADMIN CONTROLS — only shown when round NOT in reveal state
             ================================================================ --}}
        <div class="gs-full-card" x-show="gameStatus !== 'completed' && roundStatus !== 'reveal'" x-transition>
            <div class="gs-section-title">🎛️ Admin Controls</div>

            {{-- STOP ROUND button — shown only while round is active --}}
            <div x-show="isRoundActive()" x-transition
                 class="gs-alert gs-alert-warning"
                 style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div>
                    <div style="font-weight:800;">⚠️ Round chal raha hai</div>
                    <div style="font-size:0.8rem;opacity:0.8;">
                        Beech mein rokne se is round ki data save nahi hogi
                    </div>
                </div>
                <button class="gs-btn gs-btn-danger gs-btn-sm gs-btn-inline"
                        @click="stopRound()" :disabled="stoppingRound">
                    <span x-show="!stoppingRound">⛔ Round Roko</span>
                    <span x-show="stoppingRound">Rok raha hai...</span>
                </button>
            </div>

            {{-- Gender / Start new round — shown when round NOT active --}}
            <div x-show="!isRoundActive()" x-transition>
                <label class="gs-label gs-mb-8">🚻 Agla Round — Kiska round hai?</label>
                <div class="gs-gender-toggle" style="margin-bottom:16px;">
                    <button type="button" class="gs-gender-btn"
                            :class="{ active: selectedGender === 'male' }"
                            @click="selectedGender = 'male'">🕺 Mard</button>
                    <button type="button" class="gs-gender-btn"
                            :class="{ active: selectedGender === 'female' }"
                            @click="selectedGender = 'female'">💃 Aurat</button>
                </div>
                <button class="gs-btn gs-btn-primary" @click="startRound()" :disabled="startingRound">
                    <span x-show="!startingRound">🎯 Round Shuru Karo!</span>
                    <span x-show="startingRound">Shuru ho raha hai...</span>
                </button>
            </div>

            <div class="gs-divider"></div>
            <button class="gs-btn gs-btn-danger" @click="endGame()">🏁 Game Khatam Karo</button>
        </div>

        {{-- Admin's own player form — uses parent gossipAdminLobby scope (no separate poll) --}}
        @if($game->admin_player_id)
        <div class="gs-admin-player-section">

            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                <div class="gs-admin-player-badge">🎮 Aapki Baari (Admin Player)</div>
                <div x-show="genderLabel && isRoundActive()"
                     :style="genderMode==='female'
                        ? 'display:inline-flex;align-items:center;padding:4px 12px;border-radius:50px;font-size:0.78rem;font-weight:800;background:linear-gradient(135deg,#FCE7F3,#FDF2F8);color:#9D174D;border:1px solid #F9A8D4;'
                        : 'display:inline-flex;align-items:center;padding:4px 12px;border-radius:50px;font-size:0.78rem;font-weight:800;background:linear-gradient(135deg,#DBEAFE,#EFF6FF);color:#1E40AF;border:1px solid #93C5FD;'">
                    <span x-text="genderLabel"></span>
                </div>
            </div>

            {{-- LOBBY --}}
            <div x-show="isAdminLobby" x-cloak class="gs-waiting-screen" style="padding:24px 0;">
                <div class="gs-waiting-icon">🛋️</div>
                <div class="gs-waiting-title" style="font-size:1.1rem;">Lobby mein hain...</div>
                <p class="gs-text-muted">Round shuru karo upar se!</p>
            </div>

            {{-- ACTIVE ROUND — stepper + form --}}
            <div x-show="isRoundActive()" x-cloak>

                {{-- Horizontal stepper: hardcoded 3 steps (no x-for to avoid vertical rendering) --}}
                <div style="display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
                    {{-- Step 1 --}}
                    <div :style="(adminStepDone(1)
                            ? 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:#06D6A0;color:#fff;border:2px solid #06D6A0;'
                            : adminStepActive(1)
                                ? 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:var(--gs-primary);color:#fff;border:2px solid var(--gs-primary);'
                                : 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:#f5f5f5;color:#aaa;border:2px solid #ddd;')">
                        <span x-show="adminStepDone(1)">✓</span>
                        <span x-show="!adminStepDone(1)">1</span>
                    </div>
                    {{-- Connector 1→2 --}}
                    <div style="width:40px;height:3px;" :style="adminStepDone(1) ? 'background:#06D6A0' : 'background:#ddd'"></div>
                    {{-- Step 2 --}}
                    <div :style="(adminStepDone(2)
                            ? 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:#06D6A0;color:#fff;border:2px solid #06D6A0;'
                            : adminStepActive(2)
                                ? 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:var(--gs-primary);color:#fff;border:2px solid var(--gs-primary);'
                                : 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:#f5f5f5;color:#aaa;border:2px solid #ddd;')">
                        <span x-show="adminStepDone(2)">✓</span>
                        <span x-show="!adminStepDone(2)">2</span>
                    </div>
                    {{-- Connector 2→3 --}}
                    <div style="width:40px;height:3px;" :style="adminStepDone(2) ? 'background:#06D6A0' : 'background:#ddd'"></div>
                    {{-- Step 3 --}}
                    <div :style="(adminStepDone(3)
                            ? 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:#06D6A0;color:#fff;border:2px solid #06D6A0;'
                            : adminStepActive(3)
                                ? 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:var(--gs-primary);color:#fff;border:2px solid var(--gs-primary);'
                                : 'width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.9rem;background:#f5f5f5;color:#aaa;border:2px solid #ddd;')">
                        <span x-show="adminStepDone(3)">✓</span>
                        <span x-show="!adminStepDone(3)">3</span>
                    </div>
                </div>

                {{-- Submitting animation --}}
                <div x-show="isAdminSubmittingNow" x-cloak style="text-align:center;padding:28px 0;">
                    <div style="font-size:3.5rem;animation:bounce 0.5s ease-in-out infinite;">✂️</div>
                    <div style="font-weight:800;font-size:1rem;margin-top:10px;color:var(--gs-dark);">Parchi fold ho rahi hai...</div>
                    <div class="gs-dots-loader" style="margin-top:14px;justify-content:center;"><span></span><span></span><span></span></div>
                </div>

                {{-- Between steps: adminTask=null but more steps remain (~300ms gap) --}}
                <div x-show="isAdminBetweenSteps" x-cloak style="text-align:center;padding:28px 0;">
                    <div style="font-size:2.5rem;">⏳</div>
                    <div style="font-weight:700;font-size:0.95rem;margin-top:10px;color:var(--gs-dark);">Agla step aa raha hai...</div>
                    <div class="gs-dots-loader" style="margin-top:14px;justify-content:center;"><span></span><span></span><span></span></div>
                </div>

                {{-- MY TURN — input form --}}
                <div x-show="isAdminMyTurn" x-cloak class="gs-step-form">
                    {{-- Emoji icon --}}
                    <div style="text-align:center;margin-bottom:6px;">
                        <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--gs-primary),var(--gs-secondary));display:inline-flex;align-items:center;justify-content:center;font-size:1.8rem;margin-bottom:10px;box-shadow:0 8px 24px rgba(255,107,53,0.3);">
                            <span x-text="adminTask ? adminTask.emoji : ''"></span>
                        </div>
                        <div style="font-weight:900;font-size:1.2rem;color:var(--gs-dark);" x-text="adminTask ? adminTask.label : ''"></div>
                        <div style="font-size:0.85rem;color:var(--gs-muted);margin-top:4px;" x-text="adminTask ? adminTask.subtext : ''"></div>
                    </div>

                    <div class="gs-alert gs-alert-error gs-mt-8" x-show="adminError" x-text="adminError" x-cloak></div>

                    <input class="gs-input gs-input-lg" type="text"
                           x-model="adminInput"
                           :placeholder="adminTask ? adminTask.placeholder : 'Likho...'"
                           @keyup.enter="submitAdminStep()"
                           maxlength="200"
                           autocomplete="off"
                           :disabled="adminSubmitting">

                    <button class="gs-btn gs-btn-primary" style="margin-top:14px;"
                            @click="submitAdminStep()"
                            :disabled="adminSubmitting || !adminInput.trim()">
                        ✂️ Fold Kar Do!
                    </button>
                    <p style="text-align:center;font-size:0.78rem;color:var(--gs-muted);margin-top:10px;">
                        💡 Jitna funny, utna acha!
                    </p>
                </div>

                {{-- All 3 done — waiting for others --}}
                <div x-show="isAdminWaiting" x-cloak class="gs-waiting-screen" style="padding:20px 0;">
                    <div class="gs-waiting-icon">✅</div>
                    <div class="gs-waiting-title" style="font-size:1.1rem;">Sab fold kar diya!</div>
                    <p class="gs-waiting-msg">Baki khiladi likh rahe hain...</p>
                    <div class="gs-progress-bar-wrap" style="margin-top:12px;">
                        <div class="gs-progress-bar" :style="'width:' + adminProgressPercent() + '%'"></div>
                    </div>
                    <div style="font-size:0.8rem;font-weight:800;color:var(--gs-primary);margin-top:5px;" x-text="adminProgressPercent() + '%'"></div>
                    <p style="font-size:0.82rem;color:var(--gs-muted);font-weight:600;margin-top:12px;">
                        ⏳ Agla round ka intezaar karo...
                    </p>
                </div>


            </div>{{-- /isRoundActive --}}

            {{-- CELEBRATION --}}
            <div x-show="isAdminCelebration" x-cloak style="text-align:center;padding:24px 0;">
                <div style="font-size:3.5rem;animation:bounce 1s ease-in-out infinite;">🎊</div>
                <div style="font-weight:900;font-size:1.1rem;margin-top:10px;">Sab ne likh diya!</div>
                <p class="gs-text-muted" style="margin-top:6px;">Admin reveal karega abhi...</p>
            </div>

        </div>
        @endif






        {{-- Game Over --}}
        <div class="gs-full-card" x-show="gameStatus === 'completed'" x-cloak style="text-align:center;">
            <div style="font-size:3rem;margin-bottom:12px;">🎊</div>
            <h2 style="font-weight:800;margin-bottom:8px;">Game Khatam!</h2>
            <p class="gs-text-muted gs-mb-16">History mein dekho poori gossip!</p>
            <a href="{{ route('gs.admin.history.show', $game->room_code) }}"
               class="gs-btn gs-btn-secondary gs-btn-inline" style="padding:12px 24px;">
                📜 History Dekho
            </a>
            <a href="{{ route('gs.admin.games.create') }}"
               class="gs-btn gs-btn-primary gs-btn-inline gs-mt-8" style="padding:12px 24px;">
                🎮 Naya Game Banao
            </a>
        </div>

    </div>{{-- /grid --}}
</div>
@endsection
