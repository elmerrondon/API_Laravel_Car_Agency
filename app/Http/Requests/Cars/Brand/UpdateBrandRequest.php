<?php

namespace App\Http\Requests\Cars\Brand;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends FormRequest
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
            'name' => ['sometimes','required','string','min:2','max:50',Rule::unique('brands','name')->ignore($this->brand->id)],
            'description' => ['sometimes','required','string','min:3','max:500'],
            'country_id' => ['sometimes','required','integer','exists:countries,id'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
