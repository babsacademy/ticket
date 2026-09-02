<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventScannerWebAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'no-scanner' => PreventScannerWebAccess::class,
        ]);

        // Railway (and Laravel Cloud) terminate TLS at a load balancer and
        // forward plain HTTP with X-Forwarded-* headers — without trusting
        // it, Laravel can't tell the original request was HTTPS, and
        // $request->ip() returns the proxy's IP instead of the real
        // client's for every request (silently breaking every IP-keyed
        // rate limiter in AppServiceProvider). '*' is the standard,
        // documented setting for PaaS platforms whose proxy IPs aren't
        // fixed/known in advance. (AppServiceProvider's URL::forceScheme()
        // stays as a belt-and-suspenders fallback — harmless once this is
        // also correctly trusted.)
        $middleware->trustProxies(at: '*');

        // No explicit host list: the default (config('app.url') and its
        // subdomains) already resolves to whatever APP_URL is set to per
        // environment — see vendor TrustHosts::allSubdomainsOfApplicationUrl().
        $middleware->trustHosts();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            $message = 'Trop de tentatives. Veuillez patienter quelques minutes avant de réessayer.';

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            return response($message, 429);
        });
    })->create();
