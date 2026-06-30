<?php

namespace App\Http\Requests\Cars\Car;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'price' => ['required','decimal:0,2','min:1.00'],
            'mileage' => ['required','decimal:0,2'],
            'year' => ['required','integer'],
            'vin' => ['required','string','min:17','max:17','unique:cars,vin'],
            'status' => ['required','string','min:1','max:20'],
            'color_id' => ['required','integer','exists:colors,id'],
            'car_model_id' => ['required','integer','exists:car_models,id'],
            'car_type_id' => ['required','integer','exists:car_types,id'],
            'category_id' => ['required','integer','exists:categories,id'],
            'branch_id' => ['required','integer','exists:branches,id']
        ];
    }
}
