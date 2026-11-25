<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales\Currency;

use App\Rules\CaseInsensitiveUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
     * El código del catálogo se guarda en mayúsculas.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => Str::upper((string) $this->input('code'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:50', new CaseInsensitiveUnique('currencies', 'name', $this->currency->id)],
            'code' => ['sometimes', 'required', 'string', 'min:3', 'max:3', Rule::unique('currencies', 'code')->ignore($this->currency->id)],
            'is_base' => ['sometimes', 'required', 'boolean', Rule::unique('currencies')->where(fn ($query) => $query->where('is_base', true))->ignore($this->currency->id)],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
