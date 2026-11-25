<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * IP usada por la auditoría y los limitadores.
 *
 * No delega en `$request->ip()`: Laravel toma la última IP de
 * `X-Forwarded-For` y eso permite falsificarla cuando la plataforma antepone
 * la IP real a una cabecera enviada por el cliente. Esta resolución no
 * depende del proveedor: solo acepta la cabecera si el par inmediato está en
 * `TRUSTED_PROXIES` y la cadena contiene una única IP válida. Con varios
 * saltos no hay forma de distinguir la IP real sin conocer al proveedor, así
 * que se descarta la cabecera y se usa la IP del par (fail closed).
 */
final class ClientIpResolver
{
    public static function resolve(Request $request): string
    {
        $remoteAddr = $request->server('REMOTE_ADDR');
        $peer = is_string($remoteAddr) ? $remoteAddr : '';
        $proxies = TrustedProxyPolicy::entries(config('trustedproxy.proxies'));

        if ($proxies === [] || ! self::isTrustedProxy($peer, $proxies)) {
            return $peer;
        }

        $forwarded = trim((string) $request->headers->get('X-Forwarded-For', ''));

        if ($forwarded === '' || str_contains($forwarded, ',')) {
            return $peer;
        }

        return filter_var($forwarded, FILTER_VALIDATE_IP) !== false ? $forwarded : $peer;
    }

    /**
     * @param  list<string>  $proxies
     */
    private static function isTrustedProxy(string $ip, array $proxies): bool
    {
        foreach ($proxies as $proxy) {
            // El comodín no es una red: aunque config lo permita en local,
            // aquí nunca se acepta como prueba de confianza.
            if ($proxy === '*') {
                continue;
            }

            try {
                if (IpUtils::checkIp($ip, $proxy)) {
                    return true;
                }
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return false;
    }
}
