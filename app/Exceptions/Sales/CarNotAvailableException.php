<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * El coche no puede venderse porque no está disponible.
 *
 * Se renderiza como HTTP 422 (regla de negocio incumplida).
 */
final class CarNotAvailableException extends RuntimeException
{
    public static function sold(): self
    {
        return new self('The car cannot be sold because it is already marked as sold');
    }

    public static function notAvailable(): self
    {
        return new self('The car is not available for sale');
    }
}
