<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Límites de peticiones por minuto
    |--------------------------------------------------------------------------
    |
    | Se leen en los RateLimiter de AppServiceProvider. `login` se cuenta por
    | email + IP; `login_ip` acota la rotación de emails desde una misma IP;
    | `api_anon` frena a los clientes sin token y `api`/`reports` se cuentan por
    | usuario autenticado (o IP si aún no hay usuario resuelto).
    |
    */

    'login' => (int) env('RATE_LIMIT_LOGIN', 5),

    'login_ip' => (int) env('RATE_LIMIT_LOGIN_IP', 20),

    'api_anon' => (int) env('RATE_LIMIT_API_ANON', 30),

    'api' => (int) env('RATE_LIMIT_API', 120),

    'reports' => (int) env('RATE_LIMIT_REPORTS', 30),

    'reports_pdf' => (int) env('RATE_LIMIT_REPORTS_PDF', 5),

];
