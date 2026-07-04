<?php

namespace App\Http\Requests\Users\Role;

use App\Enums\Users\RoleEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($role->name, $systemRoles)){
            return false;
        }

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
            'name' => ['sometimes','required','string','min:2','max:50',Rule::unique('roles','name')->ignore($this->role->id)],
            'description' => ['sometimes','required','string','min:1','max:500']
        ];
    }
}
