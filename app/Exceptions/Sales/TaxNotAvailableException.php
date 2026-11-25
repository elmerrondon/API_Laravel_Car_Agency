<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * Uno de los impuestos dejó de estar activo entre la validación y la venta.
 *
 * Se renderiza como HTTP 409: la petición era válida cuando se recibió, pero
 * el estado del catálogo cambió antes de persistir la venta.
 */
final class TaxNotAvailableException extends RuntimeException
{
    public static function missing(): self
    {
        return new self('One or more taxes are no longer active; reload the catalog and try again');
    }
}
