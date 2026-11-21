<?php

declare(strict_types=1);

namespace App\Exceptions\Sales;

use RuntimeException;

/**
 * Ya existe una moneda base y la base de datos ha rechazado intentar crear o
 * marcar otra. Se renderiza como HTTP 409.
 */
final class BaseCurrencyAlreadyExistsException extends RuntimeException
{
    public static function create(): self
    {
        return new self('The base currency cannot be created because a base currency already exists');
    }

    public static function update(): self
    {
        return new self('The base currency cannot be updated because a base currency already exists');
    }
}
