<?php

namespace App\Http\Requests\Locations\City;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
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
            'name' => ['required','string','min:3','max:50', Rule::unique('cities', 'name')->where('state_id', $this->state_id)],
            'state_id' => ['required','integer','exists:states,id'],
            'is_active' => ['sometimes', 'required','boolean']
        ];
    }
}
