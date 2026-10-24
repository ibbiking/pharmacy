@extends('gossip-slip.layouts.app')

@section('content')
<div class="gs-layout" style="padding-top:16px;"
     x-data="gossipPlayerGame()"
     x-init="init()"
     @beforeunload.window="stopPolling()">

    {{-- ===== GENDER BADGE (no transition = no shake) ===== --}}
    <div style="max-width:430px;width:100%;margin-bottom:10px;"
         x-show="genderLabel && roundStatus === 'step1'">
        <div style="display:flex;align-items:center;justify-content:center;gap:10px;padding:10px 20px;border-radius:50px;font-weight:800;font-size:0.95rem;border:2px solid;"
             :style="genderMode==='female'
                ? 'background:linear-gradient(135deg,#FCE7F3,#FDF2F8);color:#9D174D;border-color:#F9A8D4;'
                : 'background:linear-gradient(135deg,#DBEAFE,#EFF6FF);color:#1E40AF;border-color:#93C5FD;'">
            <span x-text="genderLabel"></span>
            <span style="font-size:0.72rem;opacity:0.65;">· Round <span x-text="roundNumber"></span></span>
        </div>
    </div>

    {{-- Top strip --}}
    <div style="max-width:430px;width:100%;display:flex;align-items:center;justify-content:space-between;padding:0 4px;margin-bottom:14px;">
        <div style="font-weight:900;font-size:1rem;background:linear-gradient(135deg,var(--gs-primary),var(--gs-secondary));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
            🎭 Gossip Slip
        </div>
        <div style="font-size:0.8rem;color:var(--gs-muted);font-weight:600;">
            👤 <span x-text="playerName"></span>
        </div>
    </div>

    {{-- ===== INCOMING SWITCH REQUEST MODAL ===== --}}
    <div x-show="switchRequest" x-cloak
         style="position:fixed;inset:0;background:rgba(26,26,46,0.88);backdrop-filter:blur(8px);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;">
        <div style="background:white;border-radius:20px;padding:28px 24px;max-width:380px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,0.3);">
            <div style="font-size:3rem;margin-bottom:12px;">🔄</div>
            <h2 style="font-size:1.2rem;font-weight:900;color:var(--gs-dark);margin-bottom:8px;">Switch Request!</h2>
            <p style="color:var(--gs-muted);font-size:0.9rem;margin-bottom:20px;">
                <strong x-text="switchRequest && switchRequest.requester_name" style="color:var(--gs-primary);"></strong>
                chahte hain aapki jagha lena. Allow karein?
            </p>
            <div x-show="switchRespondResult" class="gs-alert gs-alert-info" x-text="switchRespondResult" x-cloak></div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button class="gs-btn gs-btn-danger" @click="respondSwitch('deny')" :disabled="switchRespondBusy">❌ Nahi</button>
                <button class="gs-btn gs-btn-success" @click="respondSwitch('approve')" :disabled="switchRespondBusy">✅ Switch karo</button>
            </div>
        </div>
    </div>

    {{-- Fixed-height main area — prevents any layout jump between states --}}
    <div style="max-width:430px;width:100%;min-height:420px;">

        {{-- ======== LOBBY ======== --}}
        <div class="gs-card" x-show="isLobby" x-cloak>
            <div class="gs-waiting-screen">
                <div class="gs-waiting-icon">🛋️</div>
                <div class="gs-waiting-title">Lobby mein hain!</div>
                <p class="gs-waiting-msg">Admin round shuru karega... Thoda sabr! 🎪</p>
                <div class="gs-dots-loader"><span></span><span></span><span></span></div>
            </div>
        </div>

        {{-- ======== ACTIVE ROUND ======== --}}
        <div class="gs-card" x-show="roundStatus === 'step1'" x-cloak>

            {{-- Stepper (driven by tasksDone 0→1→2→3) --}}
            <div class="gs-stepper" style="margin-bottom:24px;">
                <div class="gs-step-item" :class="{ done: stepDone(1), active: stepActive(1) }">
                    <div class="gs-step-circle"><span x-text="stepDone(1) ? '✓' : '1'"></span></div>
                    <div class="gs-step-label">Naam 👤</div>
                </div>
                <div class="gs-step-item" :class="{ done: stepDone(2), active: stepActive(2) }">
                    <div class="gs-step-circle"><span x-text="stepDone(2) ? '✓' : '2'"></span></div>
                    <div class="gs-step-label" x-text="genderMode === 'female' ? 'Harkat 💃' : 'Harkat 🕺'">Harkat</div>
                </div>
                <div class="gs-step-item" :class="{ done: stepDone(3), active: stepActive(3) }">
                    <div class="gs-step-circle"><span x-text="stepDone(3) ? '✓' : '3'"></span></div>
                    <div class="gs-step-label">Jagha 📍</div>
                </div>
            </div>

            {{-- STATE 1: Submitting fold animation --}}
            <div x-show="isSubmittingNow" x-cloak
                 style="text-align:center;padding:36px 12px;">
                <div style="font-size:3.5rem;animation:bounce 0.5s ease-in-out infinite;">✂️</div>
                <div style="font-weight:800;font-size:1.1rem;margin-top:12px;color:var(--gs-dark);">
                    Parchi fold ho rahi hai...
                </div>
                <div class="gs-dots-loader" style="margin-top:16px;justify-content:center;">
                    <span></span><span></span><span></span>
                </div>
            </div>

            {{-- STATE 2: My turn — show input form --}}
            <div x-show="isMyTurn" x-cloak class="gs-step-form">
                <div class="gs-step-header">
                    <span class="gs-step-emoji" x-text="task ? task.emoji : ''"></span>
                    <div class="gs-step-title" x-text="task ? task.label : ''"></div>
                    <div class="gs-step-sub" x-text="task ? task.subtext : ''"></div>
                </div>

                <div class="gs-alert gs-alert-error" x-show="errorMsg" x-text="errorMsg" x-cloak></div>

                <input class="gs-input gs-input-lg"
                       type="text"
                       x-model="inputValue"
                       :placeholder="task ? task.placeholder : ''"
                       @keyup.enter="submitStep()"
                       :disabled="isSubmitting"
                       maxlength="200"
                       autocomplete="off"
                       spellcheck="false">

                <button class="gs-btn gs-btn-primary"
                        style="margin-top:14px;"
                        @click="submitStep()"
                        :disabled="isSubmitting || !inputValue.trim()">
                    ✂️ Fold Kar Do!
                </button>

                <p style="text-align:center;font-size:0.78rem;color:var(--gs-muted);margin-top:10px;">
                    💡 Jitna funny, utna acha — seedha likhna! 😄
                </p>
            </div>

            {{-- STATE 3: All my tasks done, waiting for others --}}
            <div x-show="isWaiting" x-cloak class="gs-waiting-screen" style="padding:24px 12px;">
                <div class="gs-waiting-icon">✅</div>
                <div class="gs-waiting-title">Aapne sab fold kar diya!</div>
                <p class="gs-waiting-msg">Baki khiladi likh rahe hain...</p>

                <div class="gs-progress-bar-wrap">
                    <div class="gs-progress-bar" :style="'width:' + progressPercent() + '%'"></div>
                </div>
                <p style="font-size:0.85rem;font-weight:700;color:var(--gs-dark);margin-top:6px;">
                    <span x-text="doneTasks"></span>/<span x-text="totalTasks"></span> tasks complete 📜
                </p>
                <div class="gs-dots-loader" style="margin-top:20px;">
                    <span></span><span></span><span></span>
                </div>
            </div>

        </div>{{-- /active round --}}


        {{-- ======== REVEAL / CELEBRATION ======== --}}
        <div class="gs-card" x-show="isCelebration" x-cloak>
            <div class="gs-celebration">
                <span class="gs-celebration-emoji">🎊</span>
                <div class="gs-celebration-title">Gossip Slip Ready!</div>
                <p style="font-size:1rem;color:var(--gs-muted);margin:10px 0 20px;">
                    Sab ne fold kar diya! Admin ab reveal karega... 🎭
                </p>
                <div style="font-size:2rem;letter-spacing:6px;animation:bounce 1.5s ease-in-out infinite;">
                    🎉🎭🤣🎪
                </div>
            </div>
        </div>

        {{-- ======== GAME OVER ======== --}}
        <div class="gs-card" x-show="isGameOver" x-cloak>
            <div class="gs-celebration">
                <span class="gs-celebration-emoji">🏁</span>
                <div class="gs-celebration-title">Khel Khatam!</div>
                <p style="font-size:1rem;color:var(--gs-muted);margin:10px 0 20px;">Shukriya! Bahut mazaa aaya 😄</p>
                <a href="/gossip-slip" class="gs-btn gs-btn-primary gs-btn-inline" style="padding:12px 28px;">🏠 Home</a>
            </div>
        </div>

    </div>{{-- /min-height wrapper --}}

    {{-- Switch name link --}}
    <div style="text-align:center;margin-top:14px;max-width:430px;width:100%;" x-show="!isGameOver">
        <a href="/gossip-slip/join/{{ $game->room_code }}"
           style="font-size:0.78rem;color:var(--gs-muted);text-decoration:none;font-weight:600;">
            🔄 Galat naam join kiya? Switch karo
        </a>
    </div>

</div>
@endsection
