<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Http\FormRequest;

abstract class Controller
{
    /**
     * Normaliza el filtro `per_page` validado al `int` que esperan los servicios.
     *
     * Con `strict_types=1` una query string (`?per_page=5`) llegaría como `"5"`
     * y rompería la firma `?int` de los servicios, así que se castea en el borde.
     */
    protected function perPage(FormRequest $request): ?int
    {
        $perPage = $request->validated('per_page');

        return $perPage === null ? null : (int) $perPage;
    }

    /**
     * Los filtros llegan como strings desde la query string; se casan aquí,
     * en el borde HTTP, para que los servicios reciban tipos concretos.
     */
    protected function nullableInt(FormRequest $request, string $key): ?int
    {
        $value = $request->validated($key);

        return $value === null ? null : (int) $value;
    }

    protected function nullableFloat(FormRequest $request, string $key): ?float
    {
        $value = $request->validated($key);

        return $value === null ? null : (float) $value;
    }

    protected function nullableBool(FormRequest $request, string $key): ?bool
    {
        $value = $request->validated($key);

        if ($value === null) {
            return null;
        }

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
