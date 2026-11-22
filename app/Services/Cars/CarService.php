<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CarData;
use App\Enums\Audit\AuditAction;
use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Exceptions\Cars\CarStatusTransitionException;
use App\Models\Cars\Car;
use App\Models\Sales\Sale;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CarService
{
    /**
     * Relations consumed by CarResource (including nested resources).
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'color',
        'carModel.brand',
        'carType',
        'category',
        'branch.city.state.country',
    ];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{status: string|null, branch_id: int|null, brand_id: int|null, car_model_id: int|null, car_type_id: int|null, category_id: int|null, color_id: int|null, year_min: int|null, year_max: int|null, price_min: float|null, price_max: float|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, Car>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = Car::with(self::RELATIONS);

        $foreignKeys = [
            'branch_id' => $filters['branch_id'],
            'car_model_id' => $filters['car_model_id'],
            'car_type_id' => $filters['car_type_id'],
            'category_id' => $filters['category_id'],
            'color_id' => $filters['color_id'],
        ];

        foreach ($foreignKeys as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        // `brand_id` no es columna de cars: se resuelve por el modelo.
        if ($filters['brand_id'] !== null) {
            $brandId = $filters['brand_id'];

            $query->whereHas('carModel', static function (Builder $model) use ($brandId): void {
                $model->where('brand_id', $brandId);
            });
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['year_min'] !== null) {
            $query->where('year', '>=', $filters['year_min']);
        }

        if ($filters['year_max'] !== null) {
            $query->where('year', '<=', $filters['year_max']);
        }

        if ($filters['price_min'] !== null) {
            $query->where('price', '>=', $filters['price_min']);
        }

        if ($filters['price_max'] !== null) {
            $query->where('price', '<=', $filters['price_max']);
        }

        ListFilters::search($query, 'vin', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getCar(Car $car): Car
    {
        return $car->load(self::RELATIONS);
    }

    public function createCar(CarData $data): Car
    {
        $status = $data->status === null ? CarStatus::AVAILABLE : CarStatus::from($data->status);

        if ($status !== CarStatus::AVAILABLE) {
            throw CarStatusTransitionException::onCreation($status);
        }

        $arrayData = ['price' => round($data->price, 2), 'mileage' => $data->mileage, 'year' => $data->year, 'vin' => $data->vin, 'status' => $status->value, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $car = Car::create($cleanData);

        $car = $car->load(self::RELATIONS);

        $this->audit->record(AuditAction::CAR_CREATED, $car, [], $this->carAuditValues($car));

        return $car;
    }

    public function updateCar(Car $car, CarData $data): Car
    {
        $oldValues = [];

        $updated = DB::transaction(function () use ($car, $data, &$oldValues) {
            // El bloqueo evita que una venta se cree entre la comprobación y
            // la escritura: las guardas se re-evalúan con la fila bloqueada.
            $locked = Car::whereKey($car->getKey())->lockForUpdate()->firstOrFail();

            // Una venta completada congela el coche: el histórico financiero
            // no debe moverse por una edición manual posterior.
            if ($this->hasCompletedSale($locked)) {
                throw CarStatusTransitionException::lockedByCompletedSale((int) $locked->getKey());
            }

            if ($data->status !== null) {
                $target = CarStatus::from($data->status);

                if (! $this->isManualTransitionAllowed($locked->status, $target)) {
                    throw CarStatusTransitionException::manualChange($locked->status, $target);
                }
            }

            $oldValues = $this->carAuditValues($locked);

            $arrayData = ['price' => $data->price !== null ? round($data->price, 2) : null, 'mileage' => $data->mileage, 'year' => $data->year, 'vin' => $data->vin, 'status' => $data->status, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

            $cleanData = array_filter($arrayData, function ($value) {
                return $value !== null;
            });

            $locked->update($cleanData);

            return $locked->load(self::RELATIONS);
        });

        $this->audit->record(AuditAction::CAR_UPDATED, $updated, $oldValues, $this->carAuditValues($updated));

        return $updated;
    }

    public function deleteCar(Car $car): bool
    {
        $oldValues = $this->carAuditValues($car);

        $deleted = $car->delete();

        $this->audit->record(AuditAction::CAR_DELETED, $car, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * Un coche con venta completada queda congelado para la edición manual.
     */
    private function hasCompletedSale(Car $car): bool
    {
        return Sale::query()
            ->where('car_id', $car->getKey())
            ->where('status', SaleStatus::COMPLETED->value)
            ->exists();
    }

    /**
     * La edición manual solo mueve entre `available` y `reserved`; `sold` es
     * exclusivo de `SaleService`.
     */
    private function isManualTransitionAllowed(CarStatus $current, CarStatus $target): bool
    {
        return in_array($target, [CarStatus::AVAILABLE, CarStatus::RESERVED], true)
            && in_array($current, [CarStatus::AVAILABLE, CarStatus::RESERVED], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function carAuditValues(Car $car): array
    {
        return [
            'price' => (float) $car->price,
            'mileage' => (int) $car->mileage,
            'year' => (int) $car->year,
            'vin' => (string) $car->vin,
            'status' => $car->status->value,
            'color_id' => (int) $car->color_id,
            'car_model_id' => (int) $car->car_model_id,
            'car_type_id' => (int) $car->car_type_id,
            'category_id' => (int) $car->category_id,
            'branch_id' => (int) $car->branch_id,
        ];
    }
}
