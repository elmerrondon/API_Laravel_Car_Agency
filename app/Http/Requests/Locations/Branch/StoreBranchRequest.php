<?php

namespace App\Http\Requests\Locations\Branch;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
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
            'name' => ['required','string','min:3','max:50','unique:branches,name'],
            'address' => ['required','string','min:3','max:255'],
            'description' => ['nullable','string','max:300'],
            'zip_code' => ['required','string','min:3','max:20', 'alpha_dash'],
            'is_active' => ['sometimes','required','boolean'],
            'city_id' => ['required','integer','exists:cities,id']
        ];
    }
}
