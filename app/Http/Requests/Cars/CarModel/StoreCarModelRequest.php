<?php

namespace App\Http\Requests\Cars\CarModel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCarModelRequest extends FormRequest
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
            'name' => ['required','string','min:2','max:50','unique:car_models,name'],
            'description' => ['required','string','min:3','max:500'],
            'brand_id' => ['required','integer','exists:brands,id'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
