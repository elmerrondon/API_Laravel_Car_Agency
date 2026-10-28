<?php

use App\Exceptions\Sales\CarNotAvailableException;
use App\Exceptions\Sales\SaleAlreadyCancelledException;
use App\Exceptions\Sales\SaleNotCancellableException;
use App\Exceptions\Users\RoleInUseException;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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
    })->create();
