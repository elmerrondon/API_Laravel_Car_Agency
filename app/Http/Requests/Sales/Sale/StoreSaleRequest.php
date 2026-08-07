<?php

namespace App\Http\Requests\Sales\Sale;

use App\Enums\Cars\CarStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'discount_percentage' => ['required','numeric','min:0','max:100','decimal:0,2'],
            'exchange_rate' => ['required','numeric','min:0','decimal:0,2'],
            'taxes' => ['required','array','min:1'],
            'taxes.*' => ['required','integer','min:1','distinct','exists:taxes,id,is_active,1'],
            'sale_date' => ['required','date_format:Y-m-d H:i:s'],
            'car_id' => ['required','integer','min:1',Rule::exists('cars','id')->where('status',CarStatus::AVAILABLE->value)],
            'user_id' => ['required','integer','min:1','exists:users,id,is_active,1'],
            'branch_id' => ['required','integer','min:1','exists:branches,id,is_active,1'],
            'payment_currency_id' => ['required','integer','min:1','exists:currencies,id,is_active,1'],
            'payment_method_id' => ['required','integer','min:1','exists:payment_methods,id,is_active,1'],
            'notes' => ['nullable','string','min:10','max:500']
        ];
    }
}
