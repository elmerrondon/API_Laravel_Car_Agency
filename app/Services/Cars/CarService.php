<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CarData;
use App\Enums\Audit\AuditAction;
use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
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
        $arrayData = ['price' => round($data->price, 2), 'mileage' => $data->mileage, 'year' => $data->year, 'vin' => $data->vin, 'status' => $data->status, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

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
     * @return array<string, mixed>
     */
    private function carAuditValues(Car $car): array
    {
        return [
            'price' => (float) $car->price,
            'mileage' => (int) $car->mileage,
            'year' => (int) $car->year,
            'vin' => (string) $car->vin,
            'status' => $car->status instanceof CarStatus ? $car->status->value : null,
            'color_id' => (int) $car->color_id,
            'car_model_id' => (int) $car->car_model_id,
            'car_type_id' => (int) $car->car_type_id,
            'category_id' => (int) $car->category_id,
            'branch_id' => (int) $car->branch_id,
        ];
    }
}
