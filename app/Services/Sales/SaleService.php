<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\SaleData;
use App\Enums\Audit\AuditAction;
use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Exceptions\Sales\CarNotAvailableException;
use App\Exceptions\Sales\SaleAlreadyCancelledException;
use App\Exceptions\Sales\SaleNotCancellableException;
use App\Models\Cars\Car;
use App\Models\Sales\Currency;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class SaleService
{
    /**
     * Relations consumed by SaleResource (including nested resources).
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'car.color',
        'car.carModel.brand',
        'car.carType',
        'car.category',
        'car.branch.city.state.country',
        'user.roles.permissions',
        'user.branches.city.state.country',
        'branch.city.state.country',
        'baseCurrency',
        'paymentCurrency',
        'paymentMethod',
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function getAllPaginatedSales(?int $perPage = null)
    {
        return Sale::with(self::RELATIONS)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getSale(Sale $sale): Sale
    {
        return $sale->load(self::RELATIONS);
    }

    public function createSale(SaleData $data)
    {

        $sale = DB::transaction(function () use ($data) {
            $car = Car::whereKey($data->carId)->lockForUpdate()->firstOrFail();

            if ($car->status === CarStatus::SOLD) {
                throw CarNotAvailableException::sold();
            }

            if ($car->status !== CarStatus::AVAILABLE) {
                throw CarNotAvailableException::notAvailable();
            }

            $basePrice = $car->price;
            $discountPercentage = $data->discountPercentage;
            $totalDiscount = round($basePrice * ($discountPercentage / 100), 2);
            $taxableAmount = $basePrice - $totalDiscount;

            $taxesFromDb = Tax::whereIn('id', $data->taxes)->get();
            $totalTaxes = 0;
            $taxDetails = [];

            foreach ($taxesFromDb as $tax) {
                $calculatedTaxAmount = round($taxableAmount * ($tax->percentage / 100), 2);
                $totalTaxes += $calculatedTaxAmount;

                $taxDetails[] = [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'percentage' => $tax->percentage,
                    'amount_applied' => $calculatedTaxAmount,
                ];
            }

            $totalBaseAmount = $taxableAmount + $totalTaxes;
            $totalAmountPaid = round($totalBaseAmount * $data->exchangeRate, 2);

            $baseCurrency = Currency::firstWhere('is_base', true);

            $sale = Sale::create(
                [
                    'exchange_rate' => $data->exchangeRate,
                    'base_price' => $basePrice,
                    'taxes' => $taxDetails,
                    'total_taxes' => $totalTaxes,
                    'discount_percentage' => $discountPercentage,
                    'total_base_amount' => $totalBaseAmount,
                    'total_amount_paid' => $totalAmountPaid,
                    'total_discount' => $totalDiscount,
                    'status' => SaleStatus::COMPLETED,
                    'sale_date' => $data->saleDate,
                    'car_id' => $data->carId,
                    'user_id' => $data->userId,
                    'branch_id' => $data->branchId,
                    'payment_currency_id' => $data->paymentCurrencyId,
                    'base_currency_id' => $baseCurrency->id,
                    'payment_method_id' => $data->paymentMethodId,
                    'notes' => $data->notes,
                ]);

            $car->update(['status' => CarStatus::SOLD]);

            return $sale->load(self::RELATIONS);

        });

        $this->audit->record(AuditAction::SALE_CREATED, $sale, [], $this->saleAuditValues($sale));

        return $sale;
    }

    public function cancelSale(Sale $sale, string $notes): Sale
    {
        $oldValues = [];

        $cancelledSale = DB::transaction(function () use ($sale, $notes, &$oldValues) {
            $lockedSale = Sale::whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedSale->status === SaleStatus::CANCELLED) {
                throw new SaleAlreadyCancelledException;
            }

            if ($lockedSale->status !== SaleStatus::COMPLETED) {
                throw SaleNotCancellableException::withStatus($lockedSale->status);
            }

            $car = Car::whereKey($lockedSale->car_id)->lockForUpdate()->firstOrFail();

            $oldValues = [
                'status' => $lockedSale->status->value,
                'notes' => $lockedSale->notes,
                'car_id' => (int) $lockedSale->car_id,
            ];

            $lockedSale->status = SaleStatus::CANCELLED;
            $lockedSale->notes = $notes;
            $lockedSale->save();

            if ($car->status === CarStatus::SOLD) {
                $car->status = CarStatus::AVAILABLE;
                $car->save();
            }

            return $lockedSale->load(self::RELATIONS);
        });

        $this->audit->record(
            AuditAction::SALE_CANCELLED,
            $cancelledSale,
            $oldValues,
            $this->saleAuditValues($cancelledSale),
        );

        return $cancelledSale;
    }

    /**
     * @return array<string, mixed>
     */
    private function saleAuditValues(Sale $sale): array
    {
        return [
            'status' => $sale->status instanceof SaleStatus ? $sale->status->value : null,
            'total_base_amount' => (float) $sale->total_base_amount,
            'car_id' => (int) $sale->car_id,
            'branch_id' => (int) $sale->branch_id,
            'user_id' => (int) $sale->user_id,
            'sale_date' => $sale->sale_date,
        ];
    }
}
