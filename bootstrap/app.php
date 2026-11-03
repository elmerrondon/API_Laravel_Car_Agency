<?php

declare(strict_types=1);

use App\Exceptions\Reports\ReportTooLargeException;
use App\Exceptions\Sales\CarNotAvailableException;
use App\Exceptions\Sales\SaleAlreadyCancelledException;
use App\Exceptions\Sales\SaleNotCancellableException;
use App\Exceptions\Users\LastAdminException;
use App\Exceptions\Users\RoleInUseException;
use App\Enums\Audit\AuditAction;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Services\Audit\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [ForceJsonResponse::class]);

        $middleware->alias([
            'permission' => EnsureUserHasPermission::class,
            'active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(
            fn (CarNotAvailableException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 422)
        );

        $exceptions->render(
            fn (SaleAlreadyCancelledException|SaleNotCancellableException $e): JsonResponse => response()->json(['message' => $e->getMessage()], 409)
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
            $isForeignKeyViolation = (string) $e->getCode() === '23000'
                && (str_contains($message, 'foreign key') || str_contains($message, '1451'));

            return $isForeignKeyViolation
                ? response()->json(['message' => 'The record cannot be deleted because it is referenced by other records.'], 409)
                : null;
        });

        // El limitador de login responde 429 antes de llegar al controlador:
        // el intento bloqueado también queda auditado, sin guardar la clave.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/auth/login')) {
                app(AuditService::class)->record(
                    AuditAction::AUTH_LOGIN_FAILED,
                    null,
                    [],
                    ['email' => (string) $request->input('email'), 'reason' => 'throttled'],
                );
            }

            return null;
        });
    })->create();
