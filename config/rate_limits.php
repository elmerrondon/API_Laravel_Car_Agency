<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de peticiones por minuto
    |--------------------------------------------------------------------------
    |
    | Se leen en los RateLimiter de AppServiceProvider. `login` se cuenta por
    | email + IP; `api` y `reports` se cuentan por usuario autenticado (o IP
    | si aún no hay usuario resuelto).
    |
    */

    'login' => (int) env('RATE_LIMIT_LOGIN', 5),

    'api' => (int) env('RATE_LIMIT_API', 120),

    'reports' => (int) env('RATE_LIMIT_REPORTS', 30),

];
