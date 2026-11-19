<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales\Tax;

use App\Rules\CaseInsensitiveUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            'name' => ['sometimes', 'string', 'min:2', 'max:50', new CaseInsensitiveUnique('taxes', 'name', $this->tax->id)],
            'code' => ['sometimes', 'string', 'min:7', 'max:12', Rule::unique('taxes', 'code')->ignore($this->tax->id)],
            'percentage' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
