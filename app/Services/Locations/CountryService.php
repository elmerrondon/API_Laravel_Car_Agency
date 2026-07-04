<?php 

namespace App\Services\Locations;

use App\DTOs\Locations\CountryData;
use App\Models\Locations\Country;

class CountryService{

    public function getAllPaginated(?int $perPage = null){
        return Country::paginate($perPage);
    }

    public function createCountry(CountryData $data) : Country{
        $dataArray = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $country = Country::create($cleanData);

        $country->refresh();

        return $country;
    }

    public function updateCountry(Country $country, CountryData $data) : Country{
        $dataArray = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $country->update($cleanData);

        return $country;
    }

    public function deleteCountry(Country $country) : bool{
        return $country->delete();
    }

}