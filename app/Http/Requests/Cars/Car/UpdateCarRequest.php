<?php

namespace App\Http\Requests\Cars\Car;

use App\Enums\Cars\CarStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price' => ['sometimes','required','numeric','decimal:0,2','min:1'],
            'mileage' => ['sometimes','required','integer','min:0'],
            'year' => ['sometimes','required','integer'],
            'vin' => ['sometimes','required','string','min:17','max:17',Rule::unique('cars','vin')->ignore($this->car->id)],
            'status' => ['sometimes','required','string',Rule::unique(CarStatus::class)],
            'color_id' => ['sometimes','required','integer','exists:colors,id'],
            'car_model_id' => ['sometimes','required','integer','exists:car_models,id'],
            'car_type_id' => ['sometimes','required','integer','exists:car_types,id'],
            'category_id' => ['sometimes','required','integer','exists:categories,id'],
            'branch_id' => ['sometimes','required','integer','exists:branches,id']
        ];
    }
}
