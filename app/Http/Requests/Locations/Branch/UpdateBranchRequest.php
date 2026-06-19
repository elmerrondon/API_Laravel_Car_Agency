<?php

namespace App\Http\Requests\Locations\Branch;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
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
            'name' => ['sometimes','required','string','min:3','max:50',Rule::unique('branches','name')->ignore($this->branch->id)],
            'address' => ['sometimes','required','string','min:3','max:255'],
            'description' => ['sometimes','required','string','max:500'],
            'zip_code' => ['sometimes','required','string','max:20','alpha_dash'],
            'is_active' => ['sometimes','required','boolean'],
            'city_id' => ['sometimes','required','integer','exists:cities,id']
        ];
    }
}
