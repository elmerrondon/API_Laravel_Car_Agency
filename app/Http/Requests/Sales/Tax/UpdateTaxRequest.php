<?php

namespace App\Http\Requests\Sales\Tax;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRequest extends FormRequest
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
            'name' => ['sometimes','string','min:2','max:50',Rule::unique('taxes','name')->ignore($this->tax->id)],
            'code' => ['sometimes','string','min:7','max:12',Rule::unique('taxes','code')->ignore($this->tax->id)],
            'percentage' => ['sometimes','numeric','min:0','max:100','decimal:0,2'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
