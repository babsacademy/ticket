<?php

use App\Http\Controllers\Auth\TwoFactorEmailChallengeController;
use Illuminate\Support\Facades\Route;

// Email OTP challenge for admin logins (see
// App\Actions\Fortify\RedirectAdminToEmailTwoFactorChallenge). Deliberately
// `guest`-only and outside the `auth` middleware: at this point the user
// isn't logged in yet — only session('login.id') identifies who's mid-login,
// exactly like Fortify's own /two-factor-challenge routes.
Route::middleware('guest')->group(function (): void {
    Route::get('two-factor', [TwoFactorEmailChallengeController::class, 'show'])->name('two-factor.show');

    Route::middleware('throttle:two-factor')->group(function (): void {
        Route::post('two-factor/verify', [TwoFactorEmailChallengeController::class, 'verify'])->name('two-factor.verify');
        Route::post('two-factor/resend', [TwoFactorEmailChallengeController::class, 'resend'])->name('two-factor.resend');
    });
});
