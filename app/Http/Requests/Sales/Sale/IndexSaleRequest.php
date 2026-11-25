<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales\Sale;

use App\Enums\Sales\SaleStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSaleRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(SaleStatus::class)],
            'branch_id' => ['nullable', 'integer', 'min:1', 'exists:branches,id'],
            'user_id' => ['nullable', 'integer', 'min:1', 'exists:users,id'],
            'payment_method_id' => ['nullable', 'integer', 'min:1', 'exists:payment_methods,id'],
            'payment_currency_id' => ['nullable', 'integer', 'min:1', 'exists:currencies,id'],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
