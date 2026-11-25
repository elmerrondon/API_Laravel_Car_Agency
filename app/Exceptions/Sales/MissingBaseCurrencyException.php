<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * No existe una moneda base configurada y la venta no puede calcular su
 * importe en moneda base.
 *
 * Se renderiza como HTTP 409 (el sistema no está configurado para operar).
 */
final class MissingBaseCurrencyException extends RuntimeException
{
    public static function create(): self
    {
        return new self('The sale cannot be created because no base currency is configured');
    }
}
