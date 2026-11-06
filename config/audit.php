<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Retención de auditoría
    |--------------------------------------------------------------------------
    |
    | `audit:prune` elimina los registros de `audit_logs` más antiguos que
    | estos días. El scheduler lo ejecuta a diario; se puede sobrescribir con
    | `--days=N` en una ejecución puntual.
    |
    */

    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),

];
