<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->validateTicketSecretInProduction();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Railway's proxy terminates TLS and forwards plain HTTP to the
        // container, so without this Laravel would generate http:// URLs
        // (assets, redirects, signed links) behind an https:// front door.
        // Scoped to production so local `php artisan serve`/`composer dev`
        // (plain HTTP) still generates correct links.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Configure rate limiters for the guest-facing checkout form — protects
     * against spam/bot order submissions and PDF-download scraping, both
     * keyed by IP since checkout is unauthenticated.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('checkout', fn (Request $request): array => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perHour(20)->by($request->ip()),
        ]);

        RateLimiter::for('ticket-pdf', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));

        // Keyed by normalized email + IP (not just IP): a single attacker
        // IP could otherwise exhaust the bucket for one email and force
        // every other login attempt from behind the same NAT/proxy to wait
        // it out too.
        RateLimiter::for('scanner-login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip(),
        ));

        // Keyed by the raw bearer token itself (falls back to IP for the
        // rare case a request reaches here without one) rather than IP
        // alone: several scanner devices can legitimately share one IP
        // (same venue Wi-Fi), and this limiter shouldn't punish one device
        // for another's traffic.
        RateLimiter::for('scanner-api', fn (Request $request): Limit => Limit::perMinute(60)->by(
            $request->bearerToken() ?? $request->ip(),
        ));
    }

    /**
     * Refuse to boot in production with a missing or too-short
     * APP_TICKET_SECRET. TicketSignatureService::sign() already refuses to
     * sign/verify with one at the point of use — this fails even earlier
     * (before the first request is served) rather than mid-checkout.
     *
     * Real entropy can't be measured from a string alone, so this checks
     * length as a proxy (matching TicketSignatureService::MIN_SECRET_LENGTH):
     * the documented generation command, bin2hex(random_bytes(32)), always
     * produces a 64-character string, so anything shorter than 32 is
     * definitely not that.
     */
    protected function validateTicketSecretInProduction(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (strlen((string) config('tickets.secret')) < 32) {
            throw new RuntimeException(
                'APP_TICKET_SECRET est absent ou trop court pour la production (minimum 32 caractères — générez-en un avec : '
                .'php -r "echo bin2hex(random_bytes(32));").',
            );
        }
    }
}
