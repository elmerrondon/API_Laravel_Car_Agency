<?php

namespace App\Services\Cars;

use App\DTOs\Cars\CarTypeData;
use App\Models\Cars\CarType;

class CarTypeService{
    public function getAllPaginated($perPage = 15){
        return CarType::paginate($perPage);
    }

    public function create(CarTypeData $data) : CarType {
        $dataArray = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $carType = CarType::create($cleanData);

        $carType->refresh();
        
        return $carType;
    }

    public function update(CarType $carType, CarTypeData $data) : CarType {
        $dataArray = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $carType->update($cleanData);

        return $carType;
    }

    public function delete(CarType $carType) : bool{
        return $carType->delete();
    }
}