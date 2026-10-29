<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Users\User;
use App\OpenApi\ApiDocumentation;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // Un N+1 debe doler en desarrollo y en tests, no en producción:
        // allí se degrada a consulta perezosa en lugar de romper la petición.
        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(function () {
            return app()->isProduction()
              ? Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
              : Password::min(6);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('rate_limits.login'))->by(
                mb_strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        // Límite general del API agrupado por usuario (o IP si no hay token).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute((int) config('rate_limits.api'))->by(
                'api|'.($request->user()?->getAuthIdentifier() ?? $request->ip())
            );
        });

        // Los reportes generan PDFs y agregan datos: límite más estricto.
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute((int) config('rate_limits.reports'))->by(
                'reports|'.($request->user()?->getAuthIdentifier() ?? $request->ip())
            );
        });

        // La documentación se abre en local/testing; en el resto de entornos
        // exige DOCS_ENABLED=true de forma explícita.
        Gate::define('viewApiDocs', function (?User $user = null): bool {
            return app()->environment(['local', 'testing'])
                || (bool) config('docs.enabled');
        });

        // Scramble registra sus transformadores en su propio `boot()`, que
        // corre después de este. El documento necesita que el nuestro corra el
        // último para no perder los grupos ni los resúmenes en español.
        $this->app->booted(function (): void {
            Scramble::configure()
                ->withTags(ApiDocumentation::tags())
                ->withOperationTransformers(ApiDocumentation::transform(...))
                ->withDocumentTransformers(ApiDocumentation::document(...));
        });
    }
}
