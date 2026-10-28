<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Documentación OpenAPI
    |--------------------------------------------------------------------------
    |
    | Las rutas `/docs/api` y `/docs/api.json` de Scramble quedan abiertas en
    | `local` y `testing`. En cualquier otro entorno (producción incluida) el
    | Gate `viewApiDocs` las bloquea salvo que se active DOCS_ENABLED=true.
    |
    */

    'enabled' => env('DOCS_ENABLED', false),

];
