<?php

declare(strict_types=1);

namespace App\Enums\Cars;

enum CarStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Disponible',
            self::RESERVED => 'Reservado',
            self::SOLD => 'Vendido',
        };
    }
}
