<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyTwoFactorEmailCodeRequest;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use App\Services\TwoFactorCodeService;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Laravel\Fortify\Contracts\LoginResponse;

class TwoFactorEmailChallengeController extends Controller
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly TwoFactorCodeService $twoFactorCodeService,
    ) {
        //
    }

    /**
     * Show the email 2FA verification page for the admin currently mid-login.
     */
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return to_route('login');
        }

        return Inertia::render('auth/two-factor', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Verify the submitted code and, if valid, complete the login.
     */
    public function verify(VerifyTwoFactorEmailCodeRequest $request): Responsable|RedirectResponse
    {
        $user = $this->challengedUser($request);

        $result = $this->twoFactorCodeService->verify($user, $request->string('code')->toString());

        if (! $result['valid']) {
            return back()->withErrors([
                'code' => $result['reason'] === 'expired'
                    ? 'Code expiré, cliquez sur « Renvoyer le code ».'
                    : 'Le code saisi est incorrect.',
            ]);
        }

        $this->guard->login($user, (bool) $request->session()->pull('login.remember', false));

        $request->session()->forget('login.id');
        $request->session()->regenerate();

        return app(LoginResponse::class);
    }

    /**
     * Send the challenged user a fresh code, replacing any pending one.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->challengedUser($request);

        $code = $this->twoFactorCodeService->generateFor($user);

        Mail::to($user->email)->send(new TwoFactorCodeMail($code));

        return back()->with('status', 'Un nouveau code vous a été envoyé par e-mail.');
    }

    /**
     * Resolve the user currently mid-login, or abort if the session
     * doesn't reflect an in-progress two-factor challenge.
     */
    private function challengedUser(Request $request): User
    {
        $userId = $request->session()->get('login.id');

        abort_if($userId === null, 403);

        $user = User::query()->find((int) $userId);

        abort_if($user === null, 403);

        return $user;
    }
}
