<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Users\User;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonSerializable;
use Throwable;
use UnitEnum;

class AuditService
{
    /**
     * Claves que nunca deben llegar a la tabla, comparadas sin separadores y
     * en minúsculas (`access_token`, `accessToken` y `access-token` coinciden).
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'passwordconfirmation',
        'currentpassword',
        'newpassword',
        'remembertoken',
        'token',
        'accesstoken',
        'refreshtoken',
        'plaintexttoken',
        'secret',
        'apikey',
        'authorization',
    ];

    /**
     * Guarda una acción auditada. Un fallo de la auditoría nunca rompe la
     * operación de negocio: se registra en el log de la aplicación.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        AuditAction $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
    ): ?AuditLog {
        $actor ??= Auth::user();

        if (! $actor instanceof User) {
            $actor = null;
        }

        [$ipAddress, $userAgent] = $this->requestContext();

        try {
            $log = new AuditLog;

            $log->forceFill([
                'user_id' => $actor?->getKey(),
                'action' => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'old_values' => $this->redact($oldValues),
                'new_values' => $this->redact($newValues),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ])->save();

            return $log;
        } catch (Throwable $exception) {
            Log::warning('No se pudo registrar la auditoría.', [
                'action' => $action->value,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array{user_id?: int|null, action?: AuditAction|null, auditable_type?: string|null, auditable_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = AuditLog::query()
            ->with('user')
            ->orderByDesc('id');

        $userId = $filters['user_id'] ?? null;
        $action = $filters['action'] ?? null;
        $auditableType = $filters['auditable_type'] ?? null;
        $auditableId = $filters['auditable_id'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($action !== null) {
            $query->where('action', $action->value);
        }

        if ($auditableType !== null) {
            $query->where('auditable_type', $auditableType);
        }

        if ($auditableId !== null) {
            $query->where('auditable_id', $auditableId);
        }

        if ($from !== null) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to !== null) {
            $query->where('created_at', '<', Carbon::parse($to)->startOfDay()->addDay());
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function getAuditLog(AuditLog $auditLog): AuditLog
    {
        return $auditLog->load('user');
    }

    /**
     * IP y User-Agent de la petición; null en comandos de consola.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function requestContext(): array
    {
        if (! app()->bound('request') || (app()->runningInConsole() && ! app()->runningUnitTests())) {
            return [null, null];
        }

        $request = request();

        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        return [
            $ipAddress === null ? null : Str::limit($ipAddress, 45, ''),
            $userAgent === null ? null : Str::limit($userAgent, 500, ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        $clean = [];

        foreach ($values as $key => $value) {
            $clean[$key] = $this->isSensitive((string) $key)
                ? '[REDACTED]'
                : $this->redactValue($value);
        }

        return $clean;
    }

    private function redactValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->redact($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        if ($value instanceof JsonSerializable) {
            $serialized = $value->jsonSerialize();

            return is_array($serialized) ? $this->redact($serialized) : $serialized;
        }

        return $value;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', mb_strtolower($key)) ?? '';

        return in_array($normalized, self::SENSITIVE_KEYS, true);
    }
}
