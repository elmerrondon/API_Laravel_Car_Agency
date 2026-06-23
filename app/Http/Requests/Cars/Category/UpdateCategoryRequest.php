<?php

namespace App\Http\Requests\Cars\Category;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
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
            'name' => ['sometimes','required','string','min:3','max:50',Rule::unique('categories','name')->ignore($this->category->id)],
            'description' => ['sometimes','required','string','min:2','max:500'],
            'is_active' => ['sometimes','required','boolean']
        ];
    }
}
