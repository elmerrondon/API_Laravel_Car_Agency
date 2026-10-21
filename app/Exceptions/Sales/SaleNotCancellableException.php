<?php

namespace App\Exceptions\Sales;

use App\Enums\Sales\SaleStatus;
use RuntimeException;

/**
 * La venta existe pero su estado no permite cancelarla.
 *
 * Se renderiza como HTTP 409 (conflicto con el estado actual).
 */
final class SaleNotCancellableException extends RuntimeException
{
    public static function withStatus(SaleStatus $status): self
    {
        return new self("Only completed sales can be cancelled, current status: {$status->value}");
    }
}
