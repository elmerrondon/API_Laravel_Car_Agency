<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Añade `Strict-Transport-Security` cuando la petición es HTTPS.
 *
 * Detrás de un proxy TLS la petición llega a PHP por HTTP, así que la decisión
 * depende de `TrustProxies` y de `X-Forwarded-Proto`. Nunca se envía sobre HTTP
 * plano: un HSTS recibido por error dejaría al navegador sin acceso al stack
 * de desarrollo.
 */
final class AddHstsHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.hsts.enabled') || ! $request->isSecure()) {
            return $response;
        }

        $header = 'max-age='.(int) config('security.hsts.max_age');

        if (config('security.hsts.include_subdomains')) {
            $header .= '; includeSubDomains';
        }

        $response->headers->set('Strict-Transport-Security', $header);

        return $response;
    }
}
