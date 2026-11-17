<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Cars\Brand;
use App\Models\Cars\Car;
use App\Models\Cars\CarModel;
use App\Models\Cars\CarType;
use App\Models\Cars\Category;
use App\Models\Cars\Color;
use App\Models\Locations\Branch;
use App\Models\Locations\City;
use App\Models\Locations\Country;
use App\Models\Locations\State;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\OpenApi\ApiDocumentation;
use App\Support\CorsPolicy;
use App\Support\PasswordPolicy;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        // En producción, un CORS comodín (o vacío) abriría la API a cualquier
        // web. El arranque HTTP falla con un mensaje operativo; los comandos
        // artisan lo omiten porque durante el build aún no hay .env.production.
        if (! app()->runningInConsole()) {
            CorsPolicy::assertAllowedOrigins(
                app()->environment(),
                (array) config('cors.allowed_origins'),
            );
        }

        // Alias legibles para `audit_logs.auditable_type`: el log guarda el
        // alias (`user`, `car`, `brand`, `city`…), no el FQCN del modelo.
        Relation::morphMap([
            'user' => User::class,
            'role' => Role::class,
            'car' => Car::class,
            'branch' => Branch::class,
            'sale' => Sale::class,
            'brand' => Brand::class,
            'car_model' => CarModel::class,
            'car_type' => CarType::class,
            'category' => Category::class,
            'color' => Color::class,
            'currency' => Currency::class,
            'payment_method' => PaymentMethod::class,
            'tax' => Tax::class,
            'country' => Country::class,
            'state' => State::class,
            'city' => City::class,
        ]);

        // Un N+1 debe doler en desarrollo y en tests, no en producción:
        // allí se degrada a consulta perezosa en lugar de romper la petición.
        Model::preventLazyLoading(! app()->isProduction());

        // Política 100% local: nunca se consulta la API de Have I Been Pwned.
        Password::defaults(fn (): Password => PasswordPolicy::current());

        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));
            $ip = (string) $request->ip();

            // Dos ventanas: email+IP frena la fuerza bruta contra una cuenta y
            // la ventana por IP corta la rotación de emails (password spraying).
            return [
                Limit::perMinute((int) config('rate_limits.login'))->by($email.'|'.$ip),
                Limit::perMinute((int) config('rate_limits.login_ip'))->by($ip),
            ];
        });

        // Freno del tráfico anónimo. Lo aplica el middleware global
        // `ThrottleAnonymousApi` antes de `auth:sanctum`, así que aquí no sirve
        // `$request->user()`: el kernel aún no ha reenlazado la petición y su
        // user resolver devuelve null. El guard de Sanctum sí resuelve el token
        // bearer en este punto; sin esta comprobación el tráfico autenticado
        // consumiría el cupo anónimo (30/min) en vez del suyo (120/min).
        RateLimiter::for('api-anon', function (Request $request) {
            if (Auth::guard('sanctum')->user() !== null) {
                return Limit::none();
            }

            return Limit::perMinute((int) config('rate_limits.api_anon'))
                ->by('api-anon|'.$request->ip());
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

        // Los PDF son mucho más caros que el JSON (Dompdf consume CPU y memoria),
        // así que tienen su propio cupo además del general de reportes.
        RateLimiter::for('reports-pdf', function (Request $request) {
            return Limit::perMinute((int) config('rate_limits.reports_pdf'))->by(
                'reports-pdf|'.($request->user()?->getAuthIdentifier() ?? $request->ip())
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
