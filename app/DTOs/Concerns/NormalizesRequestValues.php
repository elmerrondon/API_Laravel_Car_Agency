<?php

declare(strict_types=1);

namespace App\DTOs\Concerns;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Normaliza los valores validados de un FormRequest a los tipos escalares que
 * declaran los DTOs.
 *
 * Con `strict_types=1` pasar `"5"` a un parámetro `int` lanzaría un TypeError,
 * pero la validación de Laravel acepta cadenas numéricas (y `"1"`/`"0"` para
 * los booleanos). Estos helpers castean una sola vez en el borde de entrada
 * para que los DTOs siempre reciban el tipo que declaran.
 */
trait NormalizesRequestValues
{
    private static function string(FormRequest $request, string $key): ?string
    {
        $value = $request->validated($key);

        return $value === null ? null : (string) $value;
    }

    private static function integer(FormRequest $request, string $key): ?int
    {
        $value = $request->validated($key);

        return $value === null ? null : (int) $value;
    }

    private static function number(FormRequest $request, string $key): ?float
    {
        $value = $request->validated($key);

        return $value === null ? null : (float) $value;
    }

    private static function boolean(FormRequest $request, string $key): ?bool
    {
        $value = $request->validated($key);

        return $value === null ? null : (bool) $value;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function list(FormRequest $request, string $key): ?array
    {
        $value = $request->validated($key);

        return $value === null ? null : (array) $value;
    }

    private static function requiredString(FormRequest $request, string $key): string
    {
        return (string) $request->validated($key);
    }

    private static function requiredInteger(FormRequest $request, string $key): int
    {
        return (int) $request->validated($key);
    }

    private static function requiredNumber(FormRequest $request, string $key): float
    {
        return (float) $request->validated($key);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function requiredList(FormRequest $request, string $key): array
    {
        return (array) $request->validated($key);
    }
}
