<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confianza
    |--------------------------------------------------------------------------
    |
    | Nombre que lee el middleware global `TrustProxies` en cada petición. Lista
    | de IPs o rangos CIDR separados por comas (por ejemplo `172.28.0.0/16`).
    | `*` confía en cualquier proxy: solo tiene sentido en PaaS, donde la
    | plataforma es la única que alcanza la aplicación.
    |
    | En la vía VPS el valor correcto es la red de Docker Compose: la app ve a
    | Nginx como su proxy inmediato y Nginx reenvía la IP y el esquema del
    | proxy externo. Vacío desactiva la confianza en cabeceras reenviadas.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
