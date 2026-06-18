<?php 

namespace App\Services\Locations;

use App\DTOs\Locations\CountryData;
use App\Models\Locations\Country;

class CountryService{

    public function getAllPaginated(int $perPage = 15){
        return Country::paginate($perPage);
    }

    public function getById(int $id){
        return Country::findOrFail($id);
    }

    public function create(CountryData $data) : Country{
        $dataArray = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        return Country::create($cleanData);
    }

    public function update(Country $country, CountryData $data) : Country{
        $dataArray = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $country->update($cleanData);

        return $country;
    }

    public function delete(Country $country) : bool{
        return $country->delete();
    }

}