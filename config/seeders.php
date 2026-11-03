<?php

declare(strict_types=1);

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

    /*
    |--------------------------------------------------------------------------
    | Administrador de producción
    |--------------------------------------------------------------------------
    |
    | `ProductionAdminSeeder` no usa Faker: crea (o actualiza) el administrador
    | con estos valores. En producción SIEMPRE define ADMIN_EMAIL y
    | ADMIN_PASSWORD; el resto son valores por defecto razonables. Igual que
    | arriba, se leen con `config()` para sobrevivir a `config:cache`.
    |
    */

    'admin_name' => env('ADMIN_NAME', 'Administrador'),
    'admin_last_name' => env('ADMIN_LAST_NAME', 'General'),
    'admin_document_number' => env('ADMIN_DOCUMENT_NUMBER', 'V-00000001'),
    'admin_code' => env('ADMIN_CODE', 'USR-0001'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@agency.com'),
    'admin_password' => env('ADMIN_PASSWORD', 'secret1234'),

];
