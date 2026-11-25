<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\Config\InsecureTrustedProxyConfigurationException;

/**
 * Regla de proxies de confianza, independiente del proveedor de hosting.
 *
 * En producción el comodín está prohibido: con `TRUSTED_PROXIES=*` Laravel
 * acepta `X-Forwarded-For` de cualquiera y toma la última IP de la cadena, que
 * puede ser la que inventó el cliente. La lista debe contener la IP o el rango
 * del proxy que realmente llega a la aplicación (Nginx en la vía VPS, el borde
 * de la plataforma en PaaS). Vacío es seguro: simplemente se ignoran las
 * cabeceras reenviadas.
 *
 * La comprobación corre en el arranque HTTP; los comandos artisan la omiten
 * para que `package:discover` y `optimize` funcionen durante el build.
 */
final class TrustedProxyPolicy
{
    public static function assertConfiguration(string $environment, mixed $proxies): void
    {
        if ($environment !== 'production') {
            return;
        }

        foreach (self::entries($proxies) as $proxy) {
            if ($proxy === '*') {
                throw new InsecureTrustedProxyConfigurationException;
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function entries(mixed $proxies): array
    {
        if (! is_string($proxies)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $proxies))));
    }
}
