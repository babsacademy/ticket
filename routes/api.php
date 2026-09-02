<?php

use App\Http\Controllers\Api\V1\Scanner\AuthController;
use App\Http\Controllers\Api\V1\Scanner\EventController;
use App\Http\Controllers\Api\V1\Scanner\TicketCheckinController;
use App\Http\Controllers\Api\V1\Scanner\TicketVerifyController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1/scanner')->group(function (): void {
    Route::get('ping', fn () => response()->json(['status' => 'ok', 'app' => 'TerangaTicket']));
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:scanner-login');

    Route::middleware(['auth:sanctum', 'role:scanner', 'throttle:scanner-api'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'destroy']);
        Route::get('events', [EventController::class, 'index']);
        Route::get('events/{event}/tickets', [EventController::class, 'tickets']);
        Route::post('tickets/verify', TicketVerifyController::class);
        Route::post('tickets/checkin', TicketCheckinController::class);
    });
});

// Deliberately NOT behind `throttle:checkout` (or any IP-keyed limiter):
// this is Wave's own server calling us, not a guest browser — it can be
// delivered from a shared/rotating IP pool, so per-IP throttling risks
// silently dropping real payment confirmations. It's already protected by
// signature verification instead (WavePaymentGateway::verifyWebhookSignature()).
Route::post('v1/webhooks/wave', [CheckoutController::class, 'webhook'])->name('webhooks.wave');
