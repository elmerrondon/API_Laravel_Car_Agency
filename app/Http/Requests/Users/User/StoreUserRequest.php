<?php

declare(strict_types=1);

namespace App\Http\Requests\Users\User;

use App\Rules\CaseInsensitiveUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza la identidad antes de validar: el email se guarda en
     * minúsculas y el documento y el código en mayúsculas.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->filled('email')) {
            $normalized['email'] = Str::lower((string) $this->input('email'));
        }

        if ($this->filled('document_number')) {
            $normalized['document_number'] = Str::upper((string) $this->input('document_number'));
        }

        if ($this->filled('code')) {
            $normalized['code'] = Str::upper((string) $this->input('code'));
        }

        $this->merge($normalized);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'document_number' => ['required', 'string', 'min:5', 'max:10', 'regex:/^[a-zA-Z0-9\-]+$/', (new CaseInsensitiveUnique('users', 'document_number'))->withoutTrashed()],
            'code' => ['required', 'string', 'min:6', 'max:10', 'regex:/^[a-zA-Z0-9\-]+$/', 'unique:users,code'],
            'email' => ['required', 'string', 'email', 'max:255', (new CaseInsensitiveUnique('users', 'email'))->withoutTrashed()],
            'password' => ['required', 'string', 'max:100', 'confirmed', Password::default()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'integer', 'distinct', 'exists:roles,id'],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*' => ['required', 'integer', 'distinct', 'exists:branches,id'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
