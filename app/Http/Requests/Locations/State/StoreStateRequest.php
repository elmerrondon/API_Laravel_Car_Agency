<?php

namespace App\Http\Requests\Locations\State;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStateRequest extends FormRequest
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
            'name' => ['required','string','min:3','max:50', Rule::unique('states','name')->where('country_id', $this->country_id)],
            'country_id' => ['required','integer','exists:countries,id'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
