<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Audit\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
    /**
     * Filas por lote. Un único DELETE de meses de auditoría bloquea la tabla
     * durante toda la sentencia y deja un bloat que PostgreSQL solo recupera
     * con VACUUM; por lotes la tabla vuelve a quedar disponible entre tandas.
     */
    private const BATCH_SIZE = 1000;

    /**
     * `--days` es opcional: sin valor se usa `AUDIT_RETENTION_DAYS` (90).
     */
    protected $signature = 'audit:prune
        {--days= : Días de retención. Por defecto, AUDIT_RETENTION_DAYS (90).}';

    protected $description = 'Elimina los registros de auditoría más antiguos que la retención configurada.';

    public function handle(): int
    {
        $days = $this->option('days');
        $days = $days === null ? (int) config('audit.retention_days') : (int) $days;

        if ($days < 1) {
            $this->error('El número de días debe ser un entero positivo.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $deleted = 0;

        while (true) {
            $ids = AuditLog::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            // `whereKey` acepta la colección de ids y el borrado sigue siendo
            // portable entre PostgreSQL y SQLite (sin `DELETE ... USING`).
            $batch = AuditLog::query()->whereKey($ids)->delete();

            if ($batch === 0) {
                // Otra instancia borró esas filas entre la lectura y el DELETE.
                break;
            }

            $deleted += $batch;
        }

        $this->info(sprintf(
            'Auditoría depurada: %d registros anteriores a %s eliminados.',
            $deleted,
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }
}
