<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Audit\AuditLog;
use Illuminate\Console\Command;

class PruneAuditLogs extends Command
{
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
        $deleted = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf(
            'Auditoría depurada: %d registros anteriores a %s eliminados.',
            $deleted,
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }
}
