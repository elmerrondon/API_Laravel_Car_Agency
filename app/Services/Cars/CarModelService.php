<?php 

namespace App\Services\Cars;

use App\DTOs\Cars\CarModelData;
use App\Models\Cars\CarModel;

class CarModelService {
    public function getAllPaginated(?int $perPage = null){
        return CarModel::paginate($perPage);
    }

    public function createCarModel(CarModelData $data) : CarModel{
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'brand_id' => $data->brandId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $carModel = CarModel::create($cleanData);

        $carModel->refresh();

        return $carModel;
    }

    public function updateCarModel(CarModel $carModel, CarModelData $data) : CarModel{
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'brand_id' => $data->brandId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $carModel->update($cleanData);

        return $carModel;
    }

    public function deleteCarModel(CarModel $carModel) : bool{
        return $carModel->delete();
    }
}