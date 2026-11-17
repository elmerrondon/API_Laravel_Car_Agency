<?php

declare(strict_types=1);

namespace App\Exceptions\Config;

use RuntimeException;

/**
 * La configuración de CORS es insegura para producción.
 *
 * Se lanza al arrancar la aplicación (petición HTTP) cuando
 * `CORS_ALLOWED_ORIGINS` está vacío o contiene el comodín `*`, porque eso
 * permitiría a cualquier web llamar a la API con las credenciales del usuario.
 */
final class InsecureCorsConfigurationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'CORS_ALLOWED_ORIGINS must list explicit origins in production; "*" and an empty value are not allowed.'
        );
    }
}
