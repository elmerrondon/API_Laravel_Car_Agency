<?php

declare(strict_types=1);

namespace App\Http\Requests\Locations\City;

use App\Models\Locations\City;
use App\Rules\CaseInsensitiveUnique;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCityRequest extends FormRequest
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
        /** @var City|null $city */
        $city = $this->route('city');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:50', (new CaseInsensitiveUnique('cities', 'name', $city?->id))->where('state_id', $this->input('state_id', $city?->state_id))],
            'state_id' => ['sometimes', 'required', 'integer', 'exists:states,id'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
