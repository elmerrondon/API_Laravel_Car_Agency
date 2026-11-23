<?php

declare(strict_types=1);

namespace App\Http\Requests\Sales\Sale;

use App\Enums\Cars\CarStatus;
use App\Models\Sales\Currency;
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
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            // Pagar en la moneda base implica importe pagado == importe base.
            // El servicio multiplica por la tasa, así que cualquier otro valor
            // dejaría la venta contabilizada dos veces en distinta moneda.
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'decimal:0,2', function ($attribute, $value, $fail) {
                $baseCurrency = Currency::firstWhere('is_base', true);

                if ($baseCurrency !== null
                    && (int) $this->input('payment_currency_id') === (int) $baseCurrency->id
                    && (float) $value !== 1.0) {
                    $fail('La moneda base solo admite un tipo de cambio de 1.');
                }
            }],
            'taxes' => ['required', 'array', 'min:1'],
            'taxes.*' => ['required', 'integer', 'min:1', 'distinct', 'exists:taxes,id,is_active,1'],
            // No se admiten ventas futuras ni anteriores a SALES_MIN_DATE.
            'sale_date' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now', 'after_or_equal:'.config('sales.min_date')],
            'car_id' => ['required', 'integer', 'min:1', Rule::exists('cars', 'id')->where('status', CarStatus::AVAILABLE->value)],
            // Debe coincidir con la sucursal del coche; el servicio lo verifica.
            'branch_id' => ['required', 'integer', 'min:1', 'exists:branches,id,is_active,1'],
            'payment_currency_id' => ['required', 'integer', 'min:1', 'exists:currencies,id,is_active,1'],
            'payment_method_id' => ['required', 'integer', 'min:1', 'exists:payment_methods,id,is_active,1'],
            'notes' => ['nullable', 'string', 'min:10', 'max:500'],
        ];
    }
}
