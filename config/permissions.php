<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Caché de permisos
    |--------------------------------------------------------------------------
    |
    | El middleware `permission` consulta los permisos del usuario en cada
    | petición. Guardarlos unos minutos evita un join por request; cualquier
    | cambio de roles o de permisos invalida la entrada del usuario afectado.
    | Un valor de 0 o negativo desactiva la caché.
    |
    */

    'cache_ttl' => (int) env('PERMISSIONS_CACHE_TTL', 300),

];
