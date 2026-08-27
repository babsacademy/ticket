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
    Route::get('ping', fn() => response()->json(['status' => 'ok', 'app' => 'ScanTicket']));
    Route::post('login', [AuthController::class, 'store']);

    Route::middleware(['auth:sanctum', 'role:scanner'])->group(function (): void {
        Route::get('events', [EventController::class, 'index']);
        Route::get('events/{event}/tickets', [EventController::class, 'tickets']);
        Route::post('tickets/verify', TicketVerifyController::class);
        Route::post('tickets/checkin', TicketCheckinController::class);
    });
});

Route::post('v1/webhooks/wave', [CheckoutController::class, 'webhook'])->name('webhooks.wave');
