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
use Illuminate\Validation\Rules\Password;

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
    }
}
