<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\SaleData;
use App\Enums\Audit\AuditAction;
use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Exceptions\Sales\CarNotAvailableException;
use App\Exceptions\Sales\MissingBaseCurrencyException;
use App\Exceptions\Sales\SaleAlreadyCancelledException;
use App\Exceptions\Sales\SaleBranchMismatchException;
use App\Exceptions\Sales\SaleNotCancellableException;
use App\Models\Cars\Car;
use App\Models\Sales\Currency;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use App\Services\Audit\AuditService;
use App\Services\Stats\StatsCache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
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

    public function __construct(
        private readonly AuditService $audit,
        private readonly StatsCache $statsCache,
    ) {}

    /** @return LengthAwarePaginator<int, Sale> */
    public function getAllPaginatedSales(?int $perPage = null): LengthAwarePaginator
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

    public function createSale(SaleData $data): Sale
    {

        $sale = DB::transaction(function () use ($data) {
            $car = Car::whereKey($data->carId)->lockForUpdate()->firstOrFail();

            if ($car->status === CarStatus::SOLD) {
                throw CarNotAvailableException::sold();
            }

            if ($car->status !== CarStatus::AVAILABLE) {
                throw CarNotAvailableException::notAvailable();
            }

            // La sucursal se deriva siempre del coche; el payload solo puede
            // confirmarla. Así una venta nunca queda en otra sucursal.
            if ($data->branchId !== (int) $car->branch_id) {
                throw SaleBranchMismatchException::between($data->branchId, (int) $car->branch_id);
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

            // La suma de importes ya redondeados puede arrastrar residuo
            // flotante; la columna decimal(12,2) lo truncaría en la BD y la
            // auditoría guardaría un valor distinto al persistido.
            $totalBaseAmount = round($taxableAmount + $totalTaxes, 2);
            $totalAmountPaid = round($totalBaseAmount * $data->exchangeRate, 2);

            $baseCurrency = Currency::firstWhere('is_base', true);

            if ($baseCurrency === null) {
                throw MissingBaseCurrencyException::create();
            }

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
                    // La atribución siempre es el actor autenticado; el payload
                    // no puede decidir a nombre de quién se registra la venta.
                    'user_id' => Auth::id(),
                    'branch_id' => (int) $car->branch_id,
                    'payment_currency_id' => $data->paymentCurrencyId,
                    'base_currency_id' => $baseCurrency->id,
                    'payment_method_id' => $data->paymentMethodId,
                    'notes' => $data->notes,
                ]);

            $car->update(['status' => CarStatus::SOLD]);

            return $sale->load(self::RELATIONS);

        });

        $this->audit->record(AuditAction::SALE_CREATED, $sale, [], $this->saleAuditValues($sale));
        $this->statsCache->flush();

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

            // El histórico conserva el coche aunque se haya borrado lógicamente.
            // La cancelación no debe resurrectarlo: solo se cancela la venta.
            $car = Car::withTrashed()->whereKey($lockedSale->car_id)->lockForUpdate()->firstOrFail();

            $oldValues = [
                'status' => $lockedSale->status->value,
                'notes' => $lockedSale->notes,
                'car_id' => (int) $lockedSale->car_id,
            ];

            $lockedSale->status = SaleStatus::CANCELLED;
            $lockedSale->notes = $notes;
            $lockedSale->save();

            if (! $car->trashed() && $car->status === CarStatus::SOLD) {
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
        $this->statsCache->flush();

        return $cancelledSale;
    }

    /**
     * @return array<string, mixed>
     */
    private function saleAuditValues(Sale $sale): array
    {
        return [
            'status' => $sale->status->value,
            'total_base_amount' => (float) $sale->total_base_amount,
            'car_id' => (int) $sale->car_id,
            'branch_id' => (int) $sale->branch_id,
            'user_id' => (int) $sale->user_id,
            'sale_date' => $sale->sale_date,
        ];
    }
}
