<?php

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * La venta ya fue cancelada previamente.
 *
 * Se renderiza como HTTP 409 (conflicto con el estado actual).
 */
final class SaleAlreadyCancelledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The sale has already been cancelled');
    }
}
