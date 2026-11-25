<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Users\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeIsEnforced
{
    /**
     * Si `ENFORCE_PASSWORD_CHANGE=true` y la cuenta tiene una clave temporal,
     * solo se permite consultar el perfil, cambiarla o cerrar sesión.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('security.enforce_password_change')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        $allowed = ($request->isMethod('GET') && $request->is('api/auth/me'))
            || ($request->isMethod('PUT') && $request->is('api/auth/password'))
            || ($request->isMethod('POST') && $request->is('api/auth/logout'));

        abort_unless($allowed, 403, 'You must change your password before continuing.');

        return $next($request);
    }
}
