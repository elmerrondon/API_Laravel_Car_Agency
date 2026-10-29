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
}
