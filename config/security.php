<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cambio obligatorio de contraseña
    |--------------------------------------------------------------------------
    |
    | Desactivado por defecto para no romper clientes que ya operan con el flag
    | `must_change_password` como informativo. Con `ENFORCE_PASSWORD_CHANGE=true`
    | el middleware `password.changed` solo deja consultar `/auth/me`, cambiar
    | la contraseña y cerrar sesión mientras el flag siga activo.
    |
    */

    'enforce_password_change' => (bool) env('ENFORCE_PASSWORD_CHANGE', false),

];
