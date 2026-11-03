<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

/**
 * Estrategia de seguridad Bearer para la documentación OpenAPI.
 *
 * El esquema (`bearerAuth`) se construye aquí en tiempo de ejecución y no en
 * `config/scramble.php` porque `php artisan config:cache` no puede serializar
 * objetos: si el `SecurityScheme` vive en la configuración, `php artisan optimize`
 * falla con «value at "scramble.security_strategy.1.scheme" is non-serializable».
 *
 * La configuración solo guarda el nombre de la clase y un array de opciones
 * serializable (`middleware`), que Scramble resuelve vía el contenedor.
 */
final class BearerAuthSecurityStrategy extends MiddlewareAuthSecurityStrategy
{
    /**
     * @param  list<string>  $middleware
     */
    public function __construct(array $middleware = ['auth', 'auth:*'])
    {
        parent::__construct(
            $middleware,
            SecurityScheme::http('bearer')
                ->as('bearerAuth')
                ->setDescription('Token Bearer emitido por POST /api/auth/login (Sanctum).'),
        );
    }
}
