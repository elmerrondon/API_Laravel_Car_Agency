<?php

declare(strict_types=1);

namespace App\Http\Requests\Stats;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros comunes de las estadísticas: rango de fechas y sucursal.
 *
 * Las fechas se envían juntas o no se envían, y `end_date` es inclusiva.
 */
class StatsFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d', 'required_with:end_date'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'required_with:start_date', 'after_or_equal:start_date'],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,id'],
        ];
    }

    public function startDate(): ?string
    {
        $value = $this->validated('start_date');

        return $value === null ? null : (string) $value;
    }

    public function endDate(): ?string
    {
        $value = $this->validated('end_date');

        return $value === null ? null : (string) $value;
    }

    public function branchId(): ?int
    {
        $value = $this->validated('branch_id');

        return $value === null ? null : (int) $value;
    }
}
