<?php

namespace App\Providers;

use App\Models\Users\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Password::defaults(function () {
            return app()->isProduction()
              ? Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
              : Password::min(6);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                mb_strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        // La documentación se abre en local/testing; en el resto de entornos
        // exige DOCS_ENABLED=true de forma explícita.
        Gate::define('viewApiDocs', function (?User $user = null): bool {
            return app()->environment(['local', 'testing'])
                || (bool) config('docs.enabled');
        });
    }
}
