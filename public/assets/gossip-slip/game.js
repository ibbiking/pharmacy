/* ==========================================================
   GOSSIP SLIP — Frontend Game Logic  v4
   Adaptive polling: 1.5s active → up to 5s idle (no-change backoff)
   Uses state_hash from server to detect real changes
   ========================================================== */

// Base poll interval in ms
var GS_POLL_FAST = 1500;
var GS_POLL_SLOW = 5000;  // max backoff when nothing changes
var GS_QUIET_STEPS = 3;   // polls with no change before backing off

// CSRF helpers
function gscsrf() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.content : '';
}
function gsAxiosCsrf() {
    if (typeof axios !== 'undefined') {
        axios.defaults.headers.common['X-CSRF-TOKEN'] = gscsrf();
        axios.defaults.headers.common['Accept'] = 'application/json';
    }
}
if (typeof axios !== 'undefined') gsAxiosCsrf();

// ============================================================
// TTS
// ============================================================
function getPreferredVoice() {
    if (!window.speechSynthesis) return null;
    var voices = window.speechSynthesis.getVoices();
    if (!voices.length) return null;
    var urduHindi = voices.find(function(v) {
        return ['ur-PK','hi-IN'].includes(v.lang) ||
            ['Google हिन्दी','Google اردو','Lekha','Swara','Kalpana'].some(function(n){ return v.name.includes(n); });
    });
    if (urduHindi) return urduHindi;
    var enIN = voices.find(function(v){ return v.lang === 'en-IN'; });
    if (enIN) return enIN;
    return voices[0] || null;
}
function speakText(text, onStart, onEnd) {
    if (!window.speechSynthesis) return;
    window.speechSynthesis.cancel();
    var go = function() {
        var voices = window.speechSynthesis.getVoices();
        if (!voices.length) { setTimeout(go, 100); return; }
        var u = new SpeechSynthesisUtterance(text);
        u.voice   = getPreferredVoice();
        u.rate    = 0.90; u.pitch = 1.15; u.volume = 1.0;
        u.onstart = function(){ onStart && onStart(); };
        u.onend   = function(){ onEnd && onEnd(); };
        u.onerror = function(){ onEnd && onEnd(); };
        window.speechSynthesis.speak(u);
    };
    go();
}
function stopSpeech() { if (window.speechSynthesis) window.speechSynthesis.cancel(); }
if (window.speechSynthesis) window.speechSynthesis.onvoiceschanged = function(){};

// ============================================================
// CONFETTI
// ============================================================
function confettiBurst() {
    var colors = ['#FF6B35','#FFD93D','#7B2D8B','#06D6A0','#EF476F','#118AB2'];
    var wrap = document.createElement('div');
    wrap.className = 'gs-confetti-wrap';
    document.body.appendChild(wrap);
    for (var i = 0; i < 60; i++) {
        var p = document.createElement('div');
        p.className = 'gs-confetti-piece';
        p.style.cssText = 'left:' + (Math.random()*100) + '%;top:-20px;background:' +
            colors[Math.floor(Math.random()*colors.length)] +
            ';width:' + (6+Math.random()*10) + 'px;height:' + (6+Math.random()*10) + 'px' +
            ';border-radius:' + (Math.random()>.5?'50%':'3px') +
            ';animation-duration:' + (1.5+Math.random()*2) + 's' +
            ';animation-delay:' + (Math.random()*.8) + 's;';
        wrap.appendChild(p);
    }
    setTimeout(function(){ wrap.remove(); }, 5000);
}

// ============================================================
// PLAYER GAME COMPONENT
// Adaptive polling: backs off to GS_POLL_SLOW when idle
// submitStep: POST done → clear form → let next poll bring task
// ============================================================
function gossipPlayerGame() {
    return {
        gameStatus:   'lobby',
        roundStatus:  null,
        roundNumber:  null,
        genderMode:   null,
        genderLabel:  null,
        playerName:   '',

        task:         null,
        tasksDone:    0,
        allMineDone:  false,

        totalTasks:   0,
        doneTasks:    0,

        inputValue:   '',
        isSubmitting: false,
        errorMsg:     '',

        switchRequest:       null,
        switchRespondBusy:   false,
        switchRespondResult: '',

        // Adaptive polling
        pollTimer:    null,
        pollInterval: GS_POLL_FAST,
        quietCount:   0,
        lastHash:     null,

        init() {
            gsAxiosCsrf();
            this.startPolling();
        },

        startPolling() {
            this.stopPolling();
            // Poll once immediately, then begin scheduled interval
            var self = this;
            self.poll().then(function() { self._schedulePoll(); });
        },

        stopPolling() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        resetPollRate() {
            this.pollInterval = GS_POLL_FAST;
            this.quietCount   = 0;
        },

        _schedulePoll() {
            var self = this;
            self.pollTimer = setTimeout(function() {
                self.pollTimer = null;
                self.poll().then(function() { self._schedulePoll(); });
            }, self.pollInterval);
        },

        async poll() {
            try {
                var res = await fetch('/gossip-slip/api/player/state', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': gscsrf() }
                });
                if (!res.ok) return;
                var d = await res.json();
                if (d.redirect) { window.location.href = d.redirect; return; }

                // --- Adaptive backoff ---
                var newHash = d.state_hash || null;
                if (newHash && newHash === this.lastHash) {
                    // Nothing changed — back off slowly
                    this.quietCount++;
                    if (this.quietCount >= GS_QUIET_STEPS) {
                        this.pollInterval = Math.min(this.pollInterval + 500, GS_POLL_SLOW);
                    }
                    return; // skip UI update
                }
                // State changed — reset to fast
                this.lastHash     = newHash;
                this.quietCount   = 0;
                this.pollInterval = GS_POLL_FAST;

                var prevStatus = this.roundStatus;

                this.gameStatus    = d.game_status   || 'lobby';
                this.roundStatus   = d.round_status  || null;
                this.roundNumber   = d.round_number  || null;
                this.genderMode    = d.gender_mode   || null;
                this.genderLabel   = d.gender_label  || null;
                this.playerName    = d.player_name   || '';
                this.allMineDone   = d.all_mine_done || false;
                this.totalTasks    = d.total_tasks   || 0;
                this.doneTasks     = d.done_tasks    || 0;
                this.switchRequest = d.switch_request || null;

                this.task      = (d.task !== undefined && d.task !== null) ? d.task : null;
                this.tasksDone = (typeof d.tasks_done !== 'undefined') ? d.tasks_done : this.tasksDone;

                if (prevStatus !== 'reveal' && this.roundStatus === 'reveal') {
                    setTimeout(function(){ confettiBurst(); }, 300);
                }
            } catch(e) { /* silent */ }
        },

        // POST -> clear form instantly -> poll brings next task (~1.5s)
        // NO await poll() inside try — fold animation = POST time only (200-500ms)
        async submitStep() {
            if (!this.inputValue.trim() || this.isSubmitting || !this.task) return;

            var val    = this.inputValue.trim();
            var chitId = this.task.chit_id;
            var step   = this.task.step;

            this.isSubmitting = true;
            this.errorMsg     = '';
            this.stopPolling();
            this.resetPollRate();

            try {
                await axios.post(
                    '/gossip-slip/play/submit',
                    { chit_id: chitId, step: step, value: val },
                    { timeout: 10000 }
                );
                this.inputValue = '';
                this.task       = null;
                this.tasksDone  = this.tasksDone + 1;
                this.lastHash   = null; // force next poll to update UI
            } catch(e) {
                var status = e.response ? e.response.status : 0;
                var msg = '';
                if (e.response && e.response.data && typeof e.response.data === 'object') {
                    msg = e.response.data.error || e.response.data.message || '';
                }
                this.errorMsg = msg || 'Submit nahi hua (HTTP ' + status + ') — dobara try karo.';
                var self = this;
                setTimeout(function(){ self.errorMsg = ''; }, 7000);
            } finally {
                this.isSubmitting = false;
                this.startPolling();
            }
        },

        progressPercent() {
            return this.totalTasks > 0
                ? Math.round(this.doneTasks / this.totalTasks * 100) : 0;
        },

        stepDone:   function(n){ return this.tasksDone >= n; },
        stepActive: function(n){ return this.tasksDone === n - 1; },

        async respondSwitch(action) {
            if (this.switchRespondBusy) return;
            this.switchRespondBusy = true;
            this.stopPolling();
            try {
                var r = await axios.post('/gossip-slip/play/switch/respond', { action: action });
                this.switchRespondResult = (r.data && r.data.message) ? r.data.message : 'Done!';
                this.switchRequest = null;
                this.lastHash = null;
                var self = this;
                setTimeout(function(){ self.switchRespondResult = ''; }, 4000);
                if (action === 'approve') {
                    setTimeout(function(){ window.location.href = '/gossip-slip'; }, 2000);
                }
            } catch(e) {
                this.switchRespondResult = (e.response && e.response.data && e.response.data.error)
                    ? e.response.data.error : 'Error!';
                var self = this;
                setTimeout(function(){ self.switchRespondResult = ''; }, 3000);
            } finally {
                this.switchRespondBusy = false;
                this.startPolling();
            }
        },

        get isLobby()         { return this.gameStatus === 'lobby' || !this.roundStatus; },
        get isMyTurn()        { return this.roundStatus === 'step1' && !!this.task && !this.isSubmitting; },
        get isSubmittingNow() { return this.roundStatus === 'step1' && this.isSubmitting; },
        get isWaiting()       { return this.roundStatus === 'step1' && !this.task && !this.isSubmitting; },
        get isCelebration()   { return this.roundStatus === 'reveal'; },
        get isGameOver()      { return this.gameStatus === 'completed' && this.roundStatus !== 'reveal'; },
    };
}

// ============================================================
// ADMIN LOBBY COMPONENT
// Adaptive polling + admin player task (no nested gossipPlayerGame)
// ============================================================
function gossipAdminLobby(roomCode) {
    return {
        roomCode:       roomCode,
        players:        [],
        roundStatus:    null,
        roundNumber:    null,
        genderMode:     null,
        genderLabel:    null,
        selectedGender: 'male',
        gameStatus:     'lobby',
        allComplete:    false,
        totalTasks:     0,
        doneTasks:      0,
        revealedCount:  0,
        pollTimer:      null,
        newPlayerName:  '',
        addingPlayer:   false,
        startingRound:  false,
        stoppingRound:  false,
        statusMsg:      '',
        errorMsg:       '',

        // Admin's own player task — fed from adminState, no separate poll
        adminTask:       null,
        adminTasksDone:  0,
        adminAllDone:    false,
        adminInput:      '',
        adminSubmitting: false,
        adminError:      '',

        // Adaptive polling
        pollInterval: GS_POLL_FAST,
        quietCount:   0,
        lastHash:     null,

        init() {
            gsAxiosCsrf();
            this.startPolling();
        },

        startPolling() {
            this.stopPolling();
            var self = this;
            self.poll().then(function() { self._schedulePoll(); });
        },

        stopPolling() {
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
                this.pollTimer = null;
            }
        },

        resetPollRate() {
            this.pollInterval = GS_POLL_FAST;
            this.quietCount   = 0;
        },

        _schedulePoll() {
            var self = this;
            self.pollTimer = setTimeout(function() {
                self.pollTimer = null;
                self.poll().then(function() { self._schedulePoll(); });
            }, self.pollInterval);
        },

        async poll() {
            try {
                var res = await fetch('/gossip-slip/api/admin/state/' + this.roomCode, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': gscsrf() }
                });
                if (!res.ok) return;
                var d = await res.json();

                // --- Adaptive backoff ---
                var newHash = d.state_hash || null;
                if (newHash && newHash === this.lastHash) {
                    this.quietCount++;
                    if (this.quietCount >= GS_QUIET_STEPS) {
                        this.pollInterval = Math.min(this.pollInterval + 500, GS_POLL_SLOW);
                    }
                    return;
                }
                this.lastHash     = newHash;
                this.quietCount   = 0;
                this.pollInterval = GS_POLL_FAST;

                var wasComplete     = this.allComplete;
                var prevRoundStatus = this.roundStatus;

                this.players       = d.players        || [];
                this.roundStatus   = d.round_status   || null;
                this.roundNumber   = d.round_number   || null;
                this.genderMode    = d.gender_mode    || null;
                this.genderLabel   = d.gender_label   || null;
                this.gameStatus    = d.game_status    || 'lobby';
                this.allComplete   = d.all_complete   || false;
                this.totalTasks    = d.total_tasks    || 0;
                this.doneTasks     = d.done_tasks     || 0;
                this.revealedCount = d.revealed_count || 0;

                // Admin player task
                this.adminTask      = (d.admin_player_task !== undefined && d.admin_player_task !== null)
                    ? d.admin_player_task : null;
                this.adminTasksDone = d.admin_player_tasks_done || 0;
                this.adminAllDone   = d.admin_player_all_done  || false;

                if (!wasComplete && this.allComplete) confettiBurst();
                if (prevRoundStatus !== 'reveal' && this.roundStatus === 'reveal' && this.genderMode) {
                    this.selectedGender = this.genderMode === 'male' ? 'female' : 'male';
                }
            } catch(e) { /* silent */ }
        },

        isRoundActive() { return this.roundStatus === 'step1'; },

        // Admin player helpers
        adminStepDone:   function(n){ return this.adminTasksDone >= n; },
        adminStepActive: function(n){ return this.adminTasksDone === n - 1 && this.isRoundActive(); },
        adminProgressPercent() {
            return this.totalTasks > 0 ? Math.round(this.doneTasks / this.totalTasks * 100) : 0;
        },
        get isAdminLobby()         { return !this.roundStatus || this.roundStatus === 'reveal'; },
        get isAdminMyTurn()        { return this.isRoundActive() && !!this.adminTask && !this.adminSubmitting; },
        get isAdminSubmittingNow() { return this.isRoundActive() && this.adminSubmitting; },
        get isAdminWaiting()       { return this.isRoundActive() && !this.adminTask && !this.adminSubmitting; },
        get isAdminCelebration()   { return this.roundStatus === 'reveal'; },

        // Submit admin's own player step via admin-auth route
        async submitAdminStep() {
            if (!this.adminInput.trim() || this.adminSubmitting || !this.adminTask) return;
            var val    = this.adminInput.trim();
            var chitId = this.adminTask.chit_id;
            var step   = this.adminTask.step;
            this.adminSubmitting = true;
            this.adminError      = '';
            this.stopPolling();
            this.resetPollRate();
            try {
                await axios.post(
                    '/gossip-slip/admin/games/' + this.roomCode + '/player-submit',
                    { chit_id: chitId, step: step, value: val },
                    { timeout: 10000 }
                );
                this.adminInput     = '';
                this.adminTask      = null;
                this.adminTasksDone = this.adminTasksDone + 1;
                this.lastHash       = null;
            } catch(e) {
                var status = e.response ? e.response.status : 0;
                var msg = (e.response && e.response.data && typeof e.response.data === 'object')
                    ? (e.response.data.error || e.response.data.message || '') : '';
                this.adminError = msg || 'Submit nahi hua (HTTP ' + status + ').';
                var self = this;
                setTimeout(function(){ self.adminError = ''; }, 6000);
            } finally {
                this.adminSubmitting = false;
                this.startPolling();
            }
        },

        progressPercent() {
            return this.totalTasks > 0
                ? Math.round(this.doneTasks / this.totalTasks * 100) : 0;
        },

        genderBg() {
            return this.genderMode === 'female'
                ? 'background:linear-gradient(135deg,#FCE7F3,#FDF2F8);color:#9D174D;border-color:#F9A8D4;'
                : 'background:linear-gradient(135deg,#DBEAFE,#EFF6FF);color:#1E40AF;border-color:#93C5FD;';
        },

        onlineCount() {
            return this.players.filter(function(p){ return p.is_online; }).length;
        },

        async startRound() {
            if (this.startingRound) return;
            this.startingRound = true;
            this.stopPolling();
            this.resetPollRate();
            try {
                var r = await axios.post(
                    '/gossip-slip/admin/games/' + this.roomCode + '/start-round',
                    { gender_mode: this.selectedGender },
                    { timeout: 15000 }
                );
                this.lastHash  = null;
                this.statusMsg = (r.data && r.data.message) ? r.data.message : 'Round shuru! 🎯';
                var self = this;
                setTimeout(function(){ self.statusMsg = ''; }, 4000);
            } catch(e) {
                var msg = (e.response && e.response.data)
                    ? (e.response.data.message || e.response.data.error || '') : '';
                this.errorMsg = msg || 'Round shuru nahi hua! Dobara try karo.';
                var self = this;
                setTimeout(function(){ self.errorMsg = ''; }, 5000);
            } finally {
                this.startingRound = false;
                this.startPolling();
            }
        },

        async stopRound() {
            if (this.stoppingRound) return;
            if (!confirm('Round rokna chahte hain? Players ke jawab save nahi honge.')) return;
            this.stoppingRound = true;
            this.stopPolling();
            try {
                await axios.post(
                    '/gossip-slip/admin/games/' + this.roomCode + '/finish-round',
                    {}, { timeout: 10000 }
                );
                this.lastHash  = null;
                this.statusMsg = 'Round rok diya! Naya round start karo ya game end karo.';
                var self = this;
                setTimeout(function(){ self.statusMsg = ''; }, 5000);
            } catch(e) {
                this.errorMsg = 'Round nahi ruka! Try again.';
                var self = this;
                setTimeout(function(){ self.errorMsg = ''; }, 3000);
            } finally {
                this.stoppingRound = false;
                this.startPolling();
            }
        },

        async endGame() {
            if (!confirm('Pakka game bilkul khatam karna hai?')) return;
            this.stopPolling();
            try {
                var r = await axios.post(
                    '/gossip-slip/admin/games/' + this.roomCode + '/end',
                    {}, { timeout: 10000 }
                );
                this.statusMsg = (r.data && r.data.message) ? r.data.message : 'Game khatam!';
                setTimeout(function(){ window.location.reload(); }, 1500);
            } catch(e) {
                this.errorMsg = 'Game end nahi hua! Try again.';
                this.startPolling();
            }
        },

        async addPlayer() {
            if (!this.newPlayerName.trim() || this.addingPlayer) return;
            this.addingPlayer = true;
            try {
                var r = await axios.post(
                    '/gossip-slip/admin/games/' + this.roomCode + '/add-player',
                    { name: this.newPlayerName.trim() }
                );
                this.statusMsg     = (r.data && r.data.message) ? r.data.message : 'Player add!';
                this.newPlayerName = '';
                this.lastHash      = null;
                var self = this;
                setTimeout(function(){ self.statusMsg = ''; }, 3000);
            } catch(e) {
                var msg = (e.response && e.response.data)
                    ? (e.response.data.message || e.response.data.error || '') : '';
                this.errorMsg = msg || 'Add nahi hua!';
                var self = this;
                setTimeout(function(){ self.errorMsg = ''; }, 4000);
            } finally {
                this.addingPlayer = false;
            }
        },

        async removePlayer(id, name) {
            if (this.isRoundActive()) {
                this.errorMsg = 'Round chal raha hai — pehle roko!';
                var self = this;
                setTimeout(function(){ self.errorMsg = ''; }, 4000);
                return;
            }
            if (!confirm('"' + name + '" ko hatana chahte hain?')) return;
            try {
                var r = await axios.delete(
                    '/gossip-slip/admin/games/' + this.roomCode + '/players/' + id
                );
                this.statusMsg = (r.data && r.data.message) ? r.data.message : (name + ' hata diya!');
                this.lastHash  = null;
                var self = this;
                setTimeout(function(){ self.statusMsg = ''; }, 3000);
            } catch(e) {
                var msg = (e.response && e.response.data)
                    ? (e.response.data.message || e.response.data.error || '') : '';
                this.errorMsg = msg || 'Nahi hata!';
                var self = this;
                setTimeout(function(){ self.errorMsg = ''; }, 4000);
            }
        },

        copyJoinUrl(url) {
            if (navigator.clipboard) {
                var self = this;
                navigator.clipboard.writeText(url).then(function(){
                    self.statusMsg = 'Link copy ho gaya!';
                    setTimeout(function(){ self.statusMsg = ''; }, 2000);
                });
            }
        },
    };
}

// ============================================================
// REVEAL GALLERY
// ============================================================
function gossipReveal(roomCode) {
    return {
        roomCode:   roomCode,
        showModal:  false,
        revealData: null,
        isLoading:  false,
        ttsActive:  false,

        async openChit(chitId) {
            this.isLoading = true;
            this.showModal = true;
            try {
                var r = await axios.post(
                    '/gossip-slip/admin/games/' + this.roomCode + '/chits/' + chitId + '/reveal'
                );
                this.revealData = r.data;
                var t = document.getElementById('chit-tile-' + chitId);
                if (t) t.classList.add('revealed');
            } catch(e) {
                this.showModal = false;
                alert('Reveal nahi hua!');
            } finally {
                this.isLoading = false;
            }
        },

        closeModal() { this.stopTts(); this.showModal = false; this.revealData = null; },

        speakChit() {
            if (!this.revealData) return;
            if (this.ttsActive) { this.stopTts(); return; }
            var self = this;
            speakText(this.revealData.sentence,
                function(){ self.ttsActive = true; },
                function(){ self.ttsActive = false; }
            );
        },

        stopTts() { stopSpeech(); this.ttsActive = false; },
    };
}

// ============================================================
// HISTORY TTS
// ============================================================
var historyTtsActive = null;
function historySpeak(sentence, btnEl) {
    if (historyTtsActive === sentence && window.speechSynthesis && window.speechSynthesis.speaking) {
        stopSpeech(); historyTtsActive = null;
        document.querySelectorAll('.gs-tts-btn').forEach(function(b){ b.classList.remove('tts-active'); });
        return;
    }
    document.querySelectorAll('.gs-tts-btn').forEach(function(b){ b.classList.remove('tts-active'); });
    historyTtsActive = sentence;
    btnEl.classList.add('tts-active');
    speakText(sentence,
        function(){ btnEl.classList.add('tts-active'); },
        function(){ btnEl.classList.remove('tts-active'); historyTtsActive = null; }
    );
}

// ============================================================
// CREATE GAME
// ============================================================
function gossipCreateGame() {
    return {
        playerCount:     2,
        players:         ['', ''],
        adminPlays:      false,
        adminPlayerName: '',

        updatePlayerCount: function(n) {
            var c = Math.max(2, Math.min(15, parseInt(n) || 2));
            this.playerCount = c;
            var cur = this.players.length;
            if (c > cur) {
                for (var i = cur; i < c; i++) this.players.push('');
            } else {
                this.players = this.players.slice(0, c);
            }
        },
    };
}
