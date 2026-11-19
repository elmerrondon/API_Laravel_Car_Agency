<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales\Currency;

use App\Models\Sales\Currency;
use App\Rules\CaseInsensitiveUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCurrencyRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:50', (new CaseInsensitiveUnique('currencies', 'name'))],
            'code' => ['required', 'string', 'min:3', 'max:3', 'unique:currencies,code'],
            'is_base' => ['sometimes', 'boolean', function ($attribute, $value, $fail) {
                if ($value === true && Currency::where('is_base', true)->exists()) {
                    $fail('A base currency already exists in the system. You cannot add another one.');
                }
            }],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
