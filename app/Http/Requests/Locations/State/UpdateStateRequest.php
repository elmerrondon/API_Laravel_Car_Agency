<?php

declare(strict_types=1);

namespace App\Http\Requests\Locations\State;

use App\Models\Locations\State;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStateRequest extends FormRequest
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
        /** @var State|null $state */
        $state = $this->route('state');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:50', Rule::unique('states', 'name')->where('country_id', $this->input('country_id', $state?->country_id))->ignore($state?->id)],
            'country_id' => ['sometimes', 'required', 'integer', 'exists:countries,id'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
