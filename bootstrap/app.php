<?php

declare(strict_types=1);

use App\Exceptions\Cars\CarStatusTransitionException;
use App\Exceptions\Reports\ReportTooLargeException;
use App\Exceptions\Sales\CarNotAvailableException;
use App\Exceptions\Sales\MissingBaseCurrencyException;
use App\Exceptions\Sales\SaleAlreadyCancelledException;
use App\Exceptions\Sales\SaleBranchMismatchException;
use App\Exceptions\Sales\SaleNotCancellableException;
use App\Exceptions\Sales\TaxNotAvailableException;
use App\Exceptions\Users\LastAdminException;
use App\Exceptions\Users\RoleInUseException;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AddHstsHeader;
use App\Http\Middleware\EnsurePasswordChangeIsEnforced;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ThrottleAnonymousApi;
use App\Services\Auth\LoginFailureAuditor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // `/up` vive fuera de los grupos web/api y no arrastra sesión ni CSRF:
        // comprueba base de datos y caché y responde 503 si alguna falla. Al no
        // pasar por el grupo `api`, necesita su propio limitador `health`.
        then: function (): void {
            Route::get('/up', HealthController::class)
                ->middleware('throttle:health')
                ->name('health');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // TrustProxies es global y lee la lista de proxies de
        // `config/trustedproxy.php` en cada petición. Aquí solo se fijan las
        // cabeceras que puede reenviar el proxy: IP real, host, puerto y
        // esquema. Sin `TRUSTED_PROXIES`, esas cabeceras se ignoran.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->api(prepend: [ForceJsonResponse::class]);

        // `throttle` de ruta se ordena después de `auth:sanctum`, así que el
        // tráfico sin token nunca llegaría al limitador. Este middleware global
        // frena por IP a los anónimos y deja pasar a los usuarios autenticados,
        // que conservan el límite `api` por usuario.
        $middleware->append(ThrottleAnonymousApi::class);

        // HSTS solo sobre HTTPS. Cuando el TLS termina en un proxy, la petición
        // es "secure" gracias a `TrustProxies` y `X-Forwarded-Proto`.
        $middleware->append(AddHstsHeader::class);

        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordChangeIsEnforced::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(
            fn (CarNotAvailableException|SaleBranchMismatchException|CarStatusTransitionException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 422)
        );

        $exceptions->render(
            fn (SaleAlreadyCancelledException|SaleNotCancellableException|MissingBaseCurrencyException|TaxNotAvailableException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 409)
        );

        $exceptions->render(
            fn (RoleInUseException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 409)
        );

        $exceptions->render(
            fn (LastAdminException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 409)
        );

        $exceptions->render(
            fn (ReportTooLargeException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 422)
        );

        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = mb_strtolower($e->getMessage());
            // `23000` es el SQLSTATE genérico de SQLite y `23503` el de
            // PostgreSQL para una violación de clave foránea.
            $isForeignKeyViolation = in_array((string) $e->getCode(), ['23000', '23503'], true)
                && (str_contains($message, 'foreign key') || str_contains($message, '1451'));

            return $isForeignKeyViolation
                ? response()->json(['message' => 'The record cannot be deleted because it is referenced by other records.'], 409)
                : null;
        });

        // El limitador de login responde 429 antes de llegar al controlador:
        // el intento bloqueado también queda auditado (agrupado por ventana,
        // sin guardar la clave y sin una fila por cada 429).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/auth/login')) {
                app(LoginFailureAuditor::class)->record(
                    (string) $request->input('email'),
                    'throttled',
                );
            }

            return null;
        });
    })->create();
