<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Stripe webhooks (Roadmap 4e): signature-verified, stateless, no CSRF.
Route::post('stripe/webhook', App\Http\Controllers\Billing\StripeWebhookController::class)
    ->middleware('throttle:120,1')->name('stripe.webhook');
