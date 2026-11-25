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

    /*
    |--------------------------------------------------------------------------
    | HSTS
    |--------------------------------------------------------------------------
    |
    | La cabecera `Strict-Transport-Security` solo se envía cuando la petición
    | llega por HTTPS (directo o mediante X-Forwarded-Proto de un proxy de
    | confianza). En local se deja desactivada para no fijar HTTPS en el
    | navegador contra el stack de desarrollo.
    |
    */

    'hsts' => [
        'enabled' => (bool) env('HSTS_ENABLED', false),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
    ],

];
