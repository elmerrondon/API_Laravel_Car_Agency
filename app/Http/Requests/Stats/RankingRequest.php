<?php

declare(strict_types=1);

namespace App\Http\Requests\Stats;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Filtros de un ranking más el límite dinámico de filas.
 */
class RankingRequest extends StatsFilterRequest
{
    public const DEFAULT_LIMIT = 10;

    public const MAX_LIMIT = 100;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    /**
     * Normaliza el límite validado al `int` que esperan los servicios.
     */
    public function limit(): int
    {
        $value = $this->validated('limit');

        return $value === null ? self::DEFAULT_LIMIT : (int) $value;
    }
}
