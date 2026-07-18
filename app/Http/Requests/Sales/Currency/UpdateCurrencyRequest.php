<?php

namespace App\Http\Requests\Sales\Currency;

use GuzzleHttp\Psr7\Query;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRequest extends FormRequest
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
            'name' => ['sometimes','required','string','min:2','max:50',Rule::unique('currencies','name')->ignore($this->currency->id)],
            'code' => ['sometimes','required','string','min:3','max:3',Rule::unique('currencies','name')->ignore($this->currency->id)],
            'is_base' => ['sometimes','required','boolean', Rule::unique('currencies')->where(fn ($query) => $query->where('is_base',true))->ignore($this->currency->id)],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
