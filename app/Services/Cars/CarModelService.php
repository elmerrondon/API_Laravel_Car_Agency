<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CarModelData;
use App\Models\Cars\CarModel;

class CarModelService
{
    private const BRAND_RELATION = 'brand:id,name,description,country_id,is_active';

    public function getAllPaginated(?int $perPage = null)
    {
        return CarModel::with(self::BRAND_RELATION)
            ->select(['id', 'name', 'description', 'brand_id', 'is_active'])
            ->paginate($perPage);
    }

    public function getCarModel(CarModel $carModel): CarModel
    {
        return $carModel->load(self::BRAND_RELATION);
    }

    public function createCarModel(CarModelData $data): CarModel
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'brand_id' => $data->brandId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $carModel = CarModel::create($cleanData);

        $carModel->refresh();

        return $carModel->load(self::BRAND_RELATION);
    }

    public function updateCarModel(CarModel $carModel, CarModelData $data): CarModel
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'brand_id' => $data->brandId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $carModel->update($cleanData);

        return $carModel->load(self::BRAND_RELATION);
    }

    public function deleteCarModel(CarModel $carModel): bool
    {
        return $carModel->delete();
    }
}
