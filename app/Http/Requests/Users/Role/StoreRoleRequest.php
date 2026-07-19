<?php

namespace App\Http\Requests\Users\Role;

use App\Enums\Users\RoleEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
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
            'name' => ['required','string','min:2','max:50',Rule::notIn(RoleEnum::cases()),'unique:roles,name'],
            'description' => ['required','string','min:3','max:500'],
            'permissions' => ['required','array','min:1'],
            'permissions.*' => ['required','integer','min:1',Rule::exists('permissions','id')]
        ];
    }
}
