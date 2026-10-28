<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos de los seeders
    |--------------------------------------------------------------------------
    |
    | `UserSeeder` crea los usuarios demo del entorno de desarrollo con esta
    | contraseña. Se lee desde configuración (no con `env()` directo) para que
    | siga funcionando cuando la configuración está cacheada.
    |
    */

    'user_password' => env('SEED_USER_PASSWORD', 'password'),

];
