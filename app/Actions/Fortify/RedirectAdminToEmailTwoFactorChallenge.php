<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TwoFactorCodeService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\LoginRateLimiter;

/**
 * Forces admin-role users through an email OTP challenge on every login,
 * regardless of whether they've also set up Fortify's own TOTP two-factor
 * authentication. Scanner and organizer accounts are untouched: they fall
 * straight through to the parent's normal TOTP-or-continue behavior.
 *
 * Extends (rather than duplicates) Fortify's own
 * RedirectIfTwoFactorAuthenticatable specifically to reuse its
 * validateCredentials() — same rate-limiting, Failed-event firing, and
 * failed-login exception as the rest of the login pipeline.
 */
class RedirectAdminToEmailTwoFactorChallenge extends RedirectIfTwoFactorAuthenticatable
{
    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        private readonly TwoFactorCodeService $twoFactorCodeService,
    ) {
        parent::__construct($guard, $limiter);
    }

    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle($request, $next)
    {
        $user = $this->validateCredentials($request);

        if (! $user instanceof User || $user->role !== UserRole::Admin) {
            // Not an admin (or no authenticateUsing override resolved a
            // user): defer entirely to Fortify's own TOTP-or-continue
            // logic. validateCredentials() runs again inside it, but only
            // re-verifies credentials we already know are correct — no
            // double failure handling, no meaningful cost at login volume.
            return parent::handle($request, $next);
        }

        $code = $this->twoFactorCodeService->generateFor($user);

        Mail::to($user->email)->send(new TwoFactorCodeMail($code));

        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('two-factor.show');
    }
}
