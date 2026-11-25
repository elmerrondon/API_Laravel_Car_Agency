<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `branchId` es la sucursal declarada en el payload: `SaleService` la compara
 * con la del coche y persiste siempre la sucursal del coche.
 */
readonly class SaleData
{
    use NormalizesRequestValues;

    /**
     * @param  array<array-key, mixed>  $taxes
     */
    public function __construct(public float $exchangeRate, public array $taxes, public string $saleDate, public int $carId, public int $branchId, public int $paymentCurrencyId, public int $paymentMethodId, public float $discountPercentage, public ?string $notes = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(exchangeRate: self::requiredNumber($request, 'exchange_rate'), taxes: self::requiredList($request, 'taxes'), saleDate: self::requiredString($request, 'sale_date'), carId: self::requiredInteger($request, 'car_id'), branchId: self::requiredInteger($request, 'branch_id'), paymentCurrencyId: self::requiredInteger($request, 'payment_currency_id'), paymentMethodId: self::requiredInteger($request, 'payment_method_id'), discountPercentage: self::requiredNumber($request, 'discount_percentage'), notes: self::string($request, 'notes'));
    }
}
