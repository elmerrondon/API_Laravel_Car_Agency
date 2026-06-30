<?php 

namespace App\Services\Cars;

use App\DTOs\Cars\CarData;
use App\Models\Cars\Car;

class CarService{
    public function getAllPaginated(?int $perPage = null){
        return Car::paginate($perPage);
    }

    public function create(CarData $data) : Car{
        $arrayData = ['price' => $data->price, 'mileage' => $data->mileage, 'year' => $data->mileage, 'vin' => $data->vin, 'status' => $data->status, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $car = car::create($cleanData);

        return $car;
    }

    public function update(Car $car, CarData $data) : Car{
        $arrayData = ['price' => $data->price, 'mileage' => $data->mileage, 'year' => $data->mileage, 'vin' => $data->vin, 'status' => $data->status, 'color_id' => $data->colorId, 'car_model_id' => $data->carModelId, 'car_type_id' => $data->carTypeId, 'category_id' => $data->categoryId, 'branch_id' => $data->branchId];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $car->update($cleanData);

        return $car;
    }

    public function delete(Car $car) : bool{
        return $car->delete();
    }
}