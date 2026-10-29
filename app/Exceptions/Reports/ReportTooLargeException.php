<?php

declare(strict_types=1);

namespace App\Exceptions\Reports;

use RuntimeException;

/**
 * El reporte supera el número de filas permitido.
 *
 * Se renderiza como HTTP 422: el cliente debe acotar el rango de fechas
 * o la sucursal en lugar de pedir el histórico completo en PDF.
 */
final class ReportTooLargeException extends RuntimeException
{
    public static function forSales(int $rows, int $maxRows): self
    {
        return new self(
            "The report would include {$rows} sales and the limit is {$maxRows}. Narrow the date range or filter by branch."
        );
    }
}
