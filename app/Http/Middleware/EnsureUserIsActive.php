<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Rechaza el token de una cuenta desactivada. La comprobación del login no
     * basta: un token emitido antes de la desactivación seguiría siendo válido
     * hasta expirar.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if(! $user instanceof User || ! $user->is_active, 401, 'Unauthenticated.');

        return $next($request);
    }
}
