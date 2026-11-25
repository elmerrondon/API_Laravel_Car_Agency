<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Health\HealthChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

final class HealthController extends Controller
{
    public function __construct(private readonly HealthChecker $health) {}

    /**
     * Healthcheck público: 200 si la base y la caché responden, 503 si no.
     *
     * La respuesta no incluye qué componente falló ni el error del driver; el
     * detalle queda solo en el log del servidor.
     */
    public function __invoke(): JsonResponse
    {
        $checks = $this->health->check();
        $healthy = $this->health->passes($checks);

        if (! $healthy) {
            Log::warning('Health check failed.', $checks);
        }

        return response()->json(
            ['status' => $healthy ? 'ok' : 'unavailable'],
            $healthy ? 200 : 503,
        );
    }
}
