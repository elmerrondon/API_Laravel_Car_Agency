<?php

namespace App\DTOs\Sales;

use Illuminate\Foundation\Http\FormRequest;

readonly class SaleData{
    public function __construct(public float $exchangeRate, public array $taxes, public string $saleDate, public int $carId, public int $userId, public int $branchId, public int $paymentCurrencyId, public int $paymentMethodId, public float $discountPercentage, public ?string $notes = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(exchangeRate: $request->validated('exchange_rate'), taxes: $request->validated('taxes'), saleDate: $request->validated('sale_date'), carId: $request->validated('car_id'), userId: $request->validated('user_id'), branchId: $request->validated('branch_id'), paymentCurrencyId: $request->validated('payment_currency_id'), paymentMethodId: $request->validated('payment_method_id'), discountPercentage: $request->validated('discount_percentage'), notes: $request->validated('notes'));
    }
}