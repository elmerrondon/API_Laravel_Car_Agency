<?php

declare(strict_types=1);

namespace App\Exceptions\Config;

use RuntimeException;

/**
 * La configuración de proxies de confianza es insegura para producción.
 *
 * Con `TRUSTED_PROXIES=*`, Laravel acepta `X-Forwarded-For` de cualquier
 * origen y, además, se queda con la última IP de la cadena. Un cliente que
 * envíe esa cabecera puede falsificar la IP que ven la auditoría y los
 * limitadores, sin depender de cómo reenvíe la cabecera la plataforma.
 */
final class InsecureTrustedProxyConfigurationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'TRUSTED_PROXIES must list explicit IPs or CIDRs in production; "*" is not allowed because it lets any client spoof X-Forwarded-For.'
        );
    }
}
