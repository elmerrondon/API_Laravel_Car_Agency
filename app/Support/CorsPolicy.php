<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\Config\InsecureCorsConfigurationException;

/**
 * Reglas de CORS por entorno.
 *
 * En local y testing el comodín `*` es cómodo y seguro (no hay datos reales),
 * pero en producción permitiría que cualquier web llamara a la API. La
 * comprobación se ejecuta en el arranque de cada petición HTTP; los comandos
 * artisan la omiten para que `package:discover` y `optimize` funcionen durante
 * el build, donde aún no existe `.env.production`.
 */
final class CorsPolicy
{
    /**
     * @param  array<int, string>  $origins
     *
     * @throws InsecureCorsConfigurationException
     */
    public static function assertAllowedOrigins(string $environment, array $origins): void
    {
        if ($environment !== 'production') {
            return;
        }

        if ($origins === [] || in_array('*', $origins, true)) {
            throw new InsecureCorsConfigurationException;
        }
    }
}
