<?php

namespace App\Http\Middleware;

use App\Enums\Users\PermissionEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Rechaza la petición si el usuario autenticado no tiene el permiso exigido.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $required = PermissionEnum::tryFrom($permission);

        abort_if($required === null, 500, "Unknown permission [{$permission}].");

        abort_unless($user->hasPermissionTo($required), 403, 'This action is unauthorized.');

        return $next($request);
    }
}
