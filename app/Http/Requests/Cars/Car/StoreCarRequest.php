<?php

namespace App\Http\Requests\Cars\Car;

use App\Enums\Cars\CarStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price' => ['required','numeric','decimal:0,2','min:1'],
            'mileage' => ['required','integer','min:0'],
            'year' => ['required','integer'],
            'vin' => ['required','string','min:17','max:17','regex:/^[a-zA-Z0-9]+$/',Rule::unique('cars','vin')->withoutTrashed()],
            'status' => ['required','string',Rule::enum(CarStatus::class)],
            'color_id' => ['required','integer','exists:colors,id'],
            'car_model_id' => ['required','integer','exists:car_models,id'],
            'car_type_id' => ['required','integer','exists:car_types,id'],
            'category_id' => ['required','integer','exists:categories,id'],
            'branch_id' => ['required','integer','exists:branches,id']
        ];
    }
}
