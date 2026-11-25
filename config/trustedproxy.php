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
    | `*` está prohibido en producción (TrustedProxyPolicy): permite falsificar
    | la IP mediante X-Forwarded-For. ClientIpResolver solo acepta una cabecera
    | de un único salto y, si hay más, usa la IP del proxy inmediato.
    |
    | En la vía VPS el valor correcto es la red de Docker Compose: la app ve a
    | Nginx como su proxy inmediato y Nginx reenvía la IP y el esquema del
    | proxy externo. En PaaS, la red del borde. Vacío desactiva la confianza en
    | cabeceras reenviadas (fail closed, sin granularidad por cliente).
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
