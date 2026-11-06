<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta, paginación y exportación CSV de la auditoría.
 */
final class AuditQueryService
{
    /**
     * @param  array{user_id?: int|null, action?: AuditAction|null, auditable_type?: string|null, auditable_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with('user')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getAuditLog(AuditLog $auditLog): AuditLog
    {
        return $auditLog->load('user');
    }

    /**
     * Exporta el resultado filtrado como CSV sin cargar todas las filas en
     * memoria. Las celdas se escapan para evitar inyección de fórmulas.
     *
     * @param  array{user_id?: int|null, action?: AuditAction|null, auditable_type?: string|null, auditable_id?: int|null, from?: string|null, to?: string|null}  $filters
     */
    public function exportCsv(array $filters): StreamedResponse
    {
        $query = $this->filteredQuery($filters)->orderByDesc('id');
        $filename = 'auditoria-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8 para que Excel reconozca los acentos.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'id',
                'action',
                'auditable_type',
                'auditable_id',
                'user_id',
                'old_values',
                'new_values',
                'ip_address',
                'user_agent',
                'created_at',
            ]);

            foreach ($query->cursor() as $log) {
                fputcsv($handle, $this->csvRow($log));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array{user_id?: int|null, action?: AuditAction|null, auditable_type?: string|null, auditable_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return Builder<AuditLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = AuditLog::query();

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

        return $query;
    }

    /**
     * @return list<string>
     */
    private function csvRow(AuditLog $log): array
    {
        return array_map(
            fn (string $value): string => preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value,
            [
                (string) $log->id,
                $log->action->value,
                (string) $log->auditable_type,
                $log->auditable_id === null ? '' : (string) $log->auditable_id,
                $log->user_id === null ? '' : (string) $log->user_id,
                $this->jsonCell($log->old_values),
                $this->jsonCell($log->new_values),
                (string) $log->ip_address,
                (string) $log->user_agent,
                $log->created_at->toDateTimeString(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $values
     */
    private function jsonCell(?array $values): string
    {
        return (string) json_encode(
            $values ?? [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
