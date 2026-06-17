<?php

namespace App\Http\Requests\Locations\City;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCityRequest extends FormRequest
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
        $currentStateId = $this->city->state_id;
        return [
            'name' => ['sometimes','required','string','min:3','max:50', Rule::unique('cities','name')->where('state_id', $this->input('state_id', $currentStateId))->ignore($this->city->id)],
            'state_id' => ['sometimes','required','integer','exists:states,id'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
