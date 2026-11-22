<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Caché de estadísticas
    |--------------------------------------------------------------------------
    |
    | Los ocho endpoints de `/api/stats/*` recalculan agregados en cada
    | petición. Se cachean `cache_ttl` segundos con una clave por endpoint y
    | combinación de filtros; una versión (`stats:version`) se incrementa al
    | crear o cancelar una venta para invalidar todas las entradas de golpe.
    | Un valor de 0 desactiva la caché (útil para medir consultas reales).
    |
    */

    'cache_ttl' => (int) env('STATS_CACHE_TTL', 45),

];
