<?php

namespace App\Http\Requests\Users\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
            'name' => ['sometimes','required','string','min:2','max:50'],
            'last_name' => ['sometimes','required','string','min:2','max:50'],
            'document_number' => ['sometimes','required','string','min:5','max:20','regex:/^[a-zA-Z0-9\-]+$/',Rule::unique('users','document')->ignore($this->user->id)->withoutTrashed()],
            'code' => ['sometimes','required','string','min:6','max:20','regex:/^[a-zA-Z0-9\-]+$/',Rule::unique('users','code')->ignore($this->user->id)],
            'email' => ['sometimes','required','string','email','max:255',Rule::unique('users','email')->ignore($this->user->id)->withoutTrashed()],
            'password' => ['sometimes','required','string','max:100','confirmed',Password::default()],
            'roles' => ['sometimes','array'],
            'roles.*' => ['integer','distinct','exists:roles,id'],
            'branches' => ['sometimes','array'],
            'branches.*' => ['integer','distinct','exists:branches,id'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
