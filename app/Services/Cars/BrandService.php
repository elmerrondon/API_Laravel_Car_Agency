<?php 

namespace App\Services\Cars;

use App\DTOs\Cars\BrandData;
use App\Models\Cars\Brand;

class BrandService {
    public function getAllPaginated(?int $perPage = null){
        return Brand::paginate($perPage);
    }

    public function createBrand(BrandData $data): Brand{
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'country_id' => $data->countryId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $brand = Brand::create($cleanData);

        $brand->refresh();

        return $brand;
    }

    public function updateBrand(Brand $brand, BrandData $data) :  Brand{
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'country_id' => $data->countryId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $brand->update($cleanData);

        return $brand;
    }

    public function deleteBrand(Brand $brand) : bool{
        return $brand->delete();
    }
}