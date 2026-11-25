<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * La sucursal declarada en la venta no coincide con la del coche.
 *
 * Se renderiza como HTTP 422 (regla de negocio incumplida).
 */
final class SaleBranchMismatchException extends RuntimeException
{
    public static function between(int $declaredBranch, int $carBranch): self
    {
        return new self(sprintf(
            'The sale branch (%d) does not match the car branch (%d)',
            $declaredBranch,
            $carBranch,
        ));
    }
}
