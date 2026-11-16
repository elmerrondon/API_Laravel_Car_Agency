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

    public function getAllPaginated(?int $perPage = null)
    {
        return Car::with(self::RELATIONS)
            ->orderBy('id')
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
        // Una venta completada congela el coche: el histórico financiero no
        // debe moverse por una edición manual posterior.
        if ($this->hasCompletedSale($car)) {
            throw CarStatusTransitionException::lockedByCompletedSale((int) $car->getKey());
        }

        if ($data->status !== null) {
            $target = CarStatus::from($data->status);

            if (! $this->isManualTransitionAllowed($car->status, $target)) {
                throw CarStatusTransitionException::manualChange($car->status, $target);
            }
        }

        $oldValues = $this->carAuditValues($car);

        $arrayData = ['price' => $data->price !== null ? round($data->price, 2) : null, 'mileage' => $data->mileage, 'year' => $data->year, 'vin' => $data->vin, 'status' => $data->status, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $car->update($cleanData);

        $car = $car->load(self::RELATIONS);

        $this->audit->record(AuditAction::CAR_UPDATED, $car, $oldValues, $this->carAuditValues($car));

        return $car;
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
