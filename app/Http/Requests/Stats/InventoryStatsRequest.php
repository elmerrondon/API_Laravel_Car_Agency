<?php

declare(strict_types=1);

namespace App\Http\Requests\Stats;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Inventario actual: solo admite el filtro de sucursal, sin fechas.
 */
class InventoryStatsRequest extends FormRequest
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
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,id'],
        ];
    }

    public function branchId(): ?int
    {
        $value = $this->validated('branch_id');

        return $value === null ? null : (int) $value;
    }
}
