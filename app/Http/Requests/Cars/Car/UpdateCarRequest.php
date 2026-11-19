<?php

declare(strict_types=1);

namespace App\Http\Requests\Cars\Car;

use App\Enums\Cars\CarStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El VIN se guarda en mayúsculas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('vin')) {
            $this->merge(['vin' => Str::upper((string) $this->input('vin'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:1'],
            'mileage' => ['sometimes', 'required', 'integer', 'min:0'],
            'year' => ['sometimes', 'required', 'integer', 'min:1900', 'max:2100'],
            // El VIN es único global, incluso entre coches con soft delete.
            'vin' => ['sometimes', 'required', 'string', 'min:17', 'max:17', 'regex:/^[a-zA-Z0-9]+$/', Rule::unique('cars', 'vin')->ignore($this->car->id)],
            // Edición manual solo entre `available` y `reserved`; `sold` es
            // exclusivo de `SaleService`.
            'status' => ['sometimes', 'required', 'string', Rule::in([
                CarStatus::AVAILABLE->value,
                CarStatus::RESERVED->value,
            ])],
            'color_id' => ['sometimes', 'required', 'integer', 'exists:colors,id'],
            'car_model_id' => ['sometimes', 'required', 'integer', 'exists:car_models,id'],
            'car_type_id' => ['sometimes', 'required', 'integer', 'exists:car_types,id'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'branch_id' => ['sometimes', 'required', 'integer', 'exists:branches,id'],
        ];
    }
}
