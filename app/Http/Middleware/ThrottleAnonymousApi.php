<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Límite por IP para el tráfico anónimo del API.
 *
 * Se ejecuta como middleware global (antes de `auth:sanctum`) porque el
 * middleware `throttle` de ruta se ordena después de la autenticación. Así una
 * petición sin token o con un token inválido se frena por IP antes de llegar
 * al guard; una petición autenticada delega en el límite por usuario normal.
 */
final class ThrottleAnonymousApi
{
    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        // El login tiene su propio limitador (email+IP y por IP); el preflight
        // de CORS no es tráfico de negocio y no debe consumir este cupo.
        if (! $request->is('api/*') || $request->is('api/auth/login') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'api-anon');
    }
}
