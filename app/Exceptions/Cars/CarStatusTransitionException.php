<?php

declare(strict_types=1);

namespace App\Exceptions\Cars;

use App\Enums\Cars\CarStatus;
use RuntimeException;

/**
 * El estado del coche no puede cambiarse por esa vía.
 *
 * Se renderiza como HTTP 422 (regla de negocio incumplida): el alta solo
 * admite `available`, la edición manual mueve entre `available` y `reserved`,
 * y `sold` queda reservado a `SaleService`.
 */
final class CarStatusTransitionException extends RuntimeException
{
    public static function onCreation(CarStatus $status): self
    {
        return new self(sprintf(
            'A car can only be created as %s, not as %s',
            CarStatus::AVAILABLE->value,
            $status->value,
        ));
    }

    public static function manualChange(CarStatus $current, CarStatus $target): self
    {
        return new self(sprintf(
            'The car status cannot change from %s to %s manually',
            $current->value,
            $target->value,
        ));
    }

    public static function lockedByCompletedSale(int $carId): self
    {
        return new self(sprintf(
            'The car %d has a completed sale and cannot be modified',
            $carId,
        ));
    }
}
