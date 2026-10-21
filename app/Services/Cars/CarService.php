<?php

namespace App\Services\Cars;

use App\DTOs\Cars\CarData;
use App\Models\Cars\Car;

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

    public function getAllPaginated(?int $perPage = null)
    {
        return Car::with(self::RELATIONS)->paginate($perPage);
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

        return $car;
    }

    public function updateCar(Car $car, CarData $data): Car
    {
        $arrayData = ['price' => $data->price !== null ? round($data->price, 2) : null, 'mileage' => $data->mileage, 'year' => $data->year, 'vin' => $data->vin, 'status' => $data->status, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $car->update($cleanData);

        return $car;
    }

    public function deleteCar(Car $car): bool
    {
        return $car->delete();
    }
}
