<?php

use App\Http\Controllers\GossipSlip\AdminAuthController;
use App\Http\Controllers\GossipSlip\AdminDashboardController;
use App\Http\Controllers\GossipSlip\GameStateController;
use App\Http\Controllers\GossipSlip\HistoryController;
use App\Http\Controllers\GossipSlip\PlayerController;
use Illuminate\Support\Facades\Route;

// ==========================================================
// GOSSIP SLIP: FOLD & REVEAL — Isolated Game Module Routes
// All routes under /gossip-slip prefix
// Tables prefix: gs_  |  Namespace: GossipSlip\
// ==========================================================

// Landing page — redirect to admin login or dashboard
Route::get('/gossip-slip', function () {
    return session()->has('gs_admin_id')
        ? redirect()->route('gs.admin.dashboard')
        : redirect()->route('gs.admin.login');
})->name('gs.home');

// ----------------------------------------------------------
// ADMIN AUTH (Public — no middleware)
// ----------------------------------------------------------
Route::prefix('gossip-slip/admin')->name('gs.admin.')->group(function () {
    Route::get('login',     [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('login',    [AdminAuthController::class, 'login']);
    Route::get('register',  [AdminAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AdminAuthController::class, 'register']);
    Route::post('logout',   [AdminAuthController::class, 'logout'])->name('logout');
});

// ----------------------------------------------------------
// ADMIN PROTECTED (gs.admin middleware)
// ----------------------------------------------------------
Route::prefix('gossip-slip/admin')->name('gs.admin.')->middleware('gs.admin')->group(function () {
    Route::get('dashboard',                          [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('games/create',                       [AdminDashboardController::class, 'create'])->name('games.create');
    Route::post('games',                             [AdminDashboardController::class, 'store'])->name('games.store');
    Route::get('games/{code}',                       [AdminDashboardController::class, 'lobby'])->name('games.lobby');
    Route::get('games/{code}/reveal',                [AdminDashboardController::class, 'revealPage'])->name('games.reveal');
    Route::post('games/{code}/start-round',          [AdminDashboardController::class, 'startRound'])->name('games.start-round');
    Route::post('games/{code}/finish-round',         [AdminDashboardController::class, 'finishRound'])->name('games.finish-round');
    Route::post('games/{code}/end',                  [AdminDashboardController::class, 'endGame'])->name('games.end');
    Route::post('games/{code}/add-player',           [AdminDashboardController::class, 'addPlayer'])->name('games.add-player');
    Route::delete('games/{code}/players/{playerId}', [AdminDashboardController::class, 'removePlayer'])->name('games.remove-player');
    Route::post('games/{code}/chits/{chitId}/reveal',[AdminDashboardController::class, 'revealChit'])->name('games.reveal-chit');
    Route::get('history',                            [HistoryController::class, 'index'])->name('history');
    Route::get('history/{code}',                     [HistoryController::class, 'show'])->name('history.show');
    Route::post('games/{code}/player-submit',        [AdminDashboardController::class, 'adminPlayerSubmit'])->name('games.player-submit');
});


// ----------------------------------------------------------
// PLAYER PUBLIC JOIN (no auth needed)
// ----------------------------------------------------------
Route::prefix('gossip-slip')->name('gs.player.')->group(function () {
    Route::get('join/{code}',  [PlayerController::class, 'showJoin'])->name('join');
    Route::post('join/{code}', [PlayerController::class, 'join']);
});

// ----------------------------------------------------------
// PLAYER GAME (gs.player middleware — session token required)
// ----------------------------------------------------------
Route::prefix('gossip-slip')->name('gs.player.')->middleware('gs.player')->group(function () {
    Route::get('play',               [PlayerController::class, 'play'])->name('play');
    Route::post('play/submit',        [PlayerController::class, 'submitStep'])->name('play.submit');
    Route::post('play/switch/respond',[PlayerController::class, 'respondSwitch'])->name('play.switch.respond');
});

// ----------------------------------------------------------
// JSON POLLING API (0.6-second interval from clients)
// ----------------------------------------------------------
Route::prefix('gossip-slip/api')->name('gs.api.')->group(function () {
    Route::get('player/state',        [GameStateController::class, 'playerState'])->name('player-state');
    Route::get('admin/state/{code}',  [GameStateController::class, 'adminState'])->name('admin-state');
    Route::post('player/heartbeat',   [GameStateController::class, 'heartbeat'])->name('heartbeat');
});
