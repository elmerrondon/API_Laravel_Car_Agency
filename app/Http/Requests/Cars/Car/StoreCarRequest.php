<?php

declare(strict_types=1);

namespace App\Http\Requests\Cars\Car;

use App\Enums\Cars\CarStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCarRequest extends FormRequest
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
            // `decimal(15,2)`: 13 dígitos enteros y 2 decimales.
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99999999999999.99'],
            'mileage' => ['required', 'integer', 'min:0'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            // El VIN es único global, incluso entre coches con soft delete.
            'vin' => ['required', 'string', 'min:17', 'max:17', 'regex:/^[a-zA-Z0-9]+$/', 'unique:cars,vin'],
            // Un coche siempre nace disponible; `sold` lo asigna la venta.
            'status' => ['required', 'string', Rule::in([CarStatus::AVAILABLE->value])],
            'color_id' => ['required', 'integer', 'exists:colors,id'],
            'car_model_id' => ['required', 'integer', 'exists:car_models,id'],
            'car_type_id' => ['required', 'integer', 'exists:car_types,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ];
    }
}
