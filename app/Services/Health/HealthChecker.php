<?php

declare(strict_types=1);

namespace App\Services\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Comprobaciones de salud compartidas por `/up` y `health:check`.
 *
 * Devuelve solo booleanos: los detalles de una excepción (host, credenciales,
 * mensaje del driver) nunca salen en la respuesta HTTP ni en la salida del
 * comando, que Docker puede exponer en `docker inspect`.
 */
class HealthChecker
{
    /**
     * Clave del resultado cacheado. Pública para que los tests y el comando
     * puedan inspeccionarla sin duplicar el literal.
     */
    public const CACHE_KEY = 'health:check';

    /**
     * Ventana corta: absorbe las ráfagas de healthchecks sin retrasar más de
     * unos segundos la detección de una caída.
     */
    private const CACHE_TTL_SECONDS = 10;

    /**
     * @return array{database: bool, cache: bool}
     */
    public function check(bool $fresh = false): array
    {
        if ($fresh) {
            return $this->runChecks();
        }

        $cached = $this->cachedResult();

        if ($cached !== null) {
            return $cached;
        }

        $checks = $this->runChecks();

        $this->cacheResult($checks);

        return $checks;
    }

    /**
     * @param  array<string, bool>  $checks
     */
    public function passes(array $checks): bool
    {
        return ! in_array(false, $checks, true);
    }

    /**
     * @return array{database: bool, cache: bool}|null
     */
    private function cachedResult(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            // Un store caído no puede tumbar `/up`: se ejecutan las
            // comprobaciones reales y `cache` devolverá false.
            return null;
        }

        if (! is_array($cached)
            || ! is_bool($cached['database'] ?? null)
            || ! is_bool($cached['cache'] ?? null)) {
            return null;
        }

        return ['database' => $cached['database'], 'cache' => $cached['cache']];
    }

    /**
     * @param  array{database: bool, cache: bool}  $checks
     */
    private function cacheResult(array $checks): void
    {
        try {
            Cache::put(self::CACHE_KEY, $checks, self::CACHE_TTL_SECONDS);
        } catch (Throwable) {
            // Sin caché se sigue respondiendo con el resultado en directo.
        }
    }

    /**
     * @return array{database: bool, cache: bool}
     */
    private function runChecks(): array
    {
        return [
            'database' => $this->databaseIsUp(),
            'cache' => $this->cacheIsUp(),
        ];
    }

    private function databaseIsUp(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheIsUp(): bool
    {
        $key = 'health:check:'.Str::random(8);

        try {
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === 'ok';
        } catch (Throwable) {
            return false;
        }
    }
}
