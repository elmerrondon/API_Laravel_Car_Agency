<?php

declare(strict_types=1);

namespace App\Http\Requests\Cars\Car;

use App\Enums\Cars\CarStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCarRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(CarStatus::class)],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,id'],
            'brand_id' => ['nullable', 'integer', 'min:1', 'exists:brands,id'],
            'car_model_id' => ['nullable', 'integer', 'min:1', 'exists:car_models,id'],
            'car_type_id' => ['nullable', 'integer', 'min:1', 'exists:car_types,id'],
            'category_id' => ['nullable', 'integer', 'min:1', 'exists:categories,id'],
            'color_id' => ['nullable', 'integer', 'min:1', 'exists:colors,id'],
            'year_min' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year_max' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
