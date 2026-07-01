<?php

namespace App\Http\Requests\Users\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required','string','min:2','max:50'],
            'lastname' => ['required','string','min:2','max:50'],
            'document_number' => ['required','string','min:5','max:20','regex:/^[a-zA-Z0-9\-]+$/',Rule::unique('users','document_number')->withoutTrashed()],
            'code' => ['required','string','min:6','max:20','regex:/^[a-zA-Z0-9\-]+$/','unique:users:code'],
            'email' => ['required','string','email','max:255',Rule::unique('users','email')->withoutTrashed()],
            'password' => ['required','string','max:100','confirmed',Password::default()],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
