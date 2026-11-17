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
     * @return array{database: bool, cache: bool}
     */
    public function check(): array
    {
        return [
            'database' => $this->databaseIsUp(),
            'cache' => $this->cacheIsUp(),
        ];
    }

    /**
     * @param  array<string, bool>  $checks
     */
    public function passes(array $checks): bool
    {
        return ! in_array(false, $checks, true);
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
