<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\Audit\AuditAction;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Auditoría de fallos de login agrupada por ventana.
 *
 * Un ataque de *password spraying* puede generar cientos de fallos y otros
 * tantos 429 por minuto. Insertar una fila por intento convierte la auditoría
 * en un vector de amplificación, así que se escribe como máximo una fila por
 * combinación de IP, motivo y minuto. La operación de login nunca falla porque
 * la caché o la auditoría no estén disponibles.
 */
final class LoginFailureAuditor
{
    /**
     * Tamaño de la ventana de agrupación, alineada al minuto.
     */
    private const WINDOW_SECONDS = 60;

    public function __construct(private readonly AuditService $audit) {}

    public function record(string $email, string $reason): void
    {
        $window = intdiv(now()->getTimestamp(), self::WINDOW_SECONDS);
        $key = 'audit:login-failure:'.sha1(implode('|', [
            $this->clientIp() ?? 'unknown',
            $reason,
            (string) $window,
        ]));

        try {
            if (! Cache::add($key, true, self::WINDOW_SECONDS)) {
                return;
            }
        } catch (Throwable) {
            // Si la caché falla, se prefiere una fila de más a romper el login.
        }

        $this->audit->record(
            AuditAction::AUTH_LOGIN_FAILED,
            null,
            [],
            ['email' => $email, 'reason' => $reason],
        );
    }

    private function clientIp(): ?string
    {
        return app()->bound('request') ? request()->ip() : null;
    }
}
