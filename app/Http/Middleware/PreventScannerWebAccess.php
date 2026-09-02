<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scanner accounts exist solely to authenticate against the Flutter app's
 * API — they must never hold a web session. Logs the scanner out (not just
 * redirects) before sending them to /login: leaving them authenticated
 * would bounce them straight back here on their very next request, since
 * the login page's own `guest` middleware redirects an authenticated user
 * away from it — an infinite loop.
 */
class PreventScannerWebAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::Scanner) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => "Les comptes scanner n'ont pas accès à cet espace — utilisez l'application mobile.",
        ]);
    }
}
