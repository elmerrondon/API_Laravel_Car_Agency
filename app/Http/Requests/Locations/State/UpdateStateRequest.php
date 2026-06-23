<?php

namespace App\Http\Requests\Locations\State;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStateRequest extends FormRequest
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
        $currenCountryId = $this->state->country_id;
        return [
            'name' => ['sometimes','required','string','min:3','max:50', Rule::unique('states','name')->where('country_id', $this->input('country_id', $currenCountryId))->ignore($this->state->id)],
            'country_id' => ['sometimes','required','integer','exists:countries,id'],
            'is_active' => ['sometimes', 'required', 'boolean']
        ];
    }
}
