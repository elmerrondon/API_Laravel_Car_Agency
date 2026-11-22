<?php

declare(strict_types=1);

namespace App\Services\Stats;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caché de los agregados de `/api/stats/*`.
 *
 * La clave combina el endpoint y los filtros normalizados, y lleva delante una
 * versión global. Incrementar la versión invalida todas las combinaciones sin
 * recorrer ni borrar claves una a una (y sin tocar la caché de permisos, que
 * vive en el mismo store). La versión se guarda sin caducidad; el TTL solo se
 * aplica a los valores cacheados.
 */
final class StatsCache
{
    private const VERSION_KEY = 'stats:version';

    /**
     * @template T
     *
     * @param  array<string, mixed>  $filters
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $endpoint, array $filters, Closure $callback): mixed
    {
        $ttl = (int) config('stats.cache_ttl');

        if ($ttl <= 0) {
            return $callback();
        }

        /** @var T $value */
        $value = Cache::remember($this->key($endpoint, $filters), $ttl, $callback);

        return $value;
    }

    /**
     * Invalida todos los agregados. Se llama después del commit de una venta.
     */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function key(string $endpoint, array $filters): string
    {
        ksort($filters);

        return sprintf(
            'stats:v%d:%s:%s',
            $this->version(),
            $endpoint,
            md5((string) json_encode($filters)),
        );
    }
}
