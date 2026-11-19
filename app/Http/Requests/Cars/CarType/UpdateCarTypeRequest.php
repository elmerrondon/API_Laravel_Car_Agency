<?php

declare(strict_types=1);

namespace App\Http\Requests\Cars\CarType;

use App\Models\Cars\CarType;
use App\Rules\CaseInsensitiveUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCarTypeRequest extends FormRequest
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
        $carType = $this->route('car_type');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:50', new CaseInsensitiveUnique('car_types', 'name', $carType instanceof CarType ? $carType->id : null)],
            'description' => ['sometimes', 'required', 'string', 'min:2', 'max:500'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
