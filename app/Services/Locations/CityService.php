<?php 

namespace App\Services\Locations;

use App\DTOs\Locations\CityData;
use App\Models\Locations\City;

class CityService{

    public function getAllPaginated(int $perPage = 15){
        return City::paginate($perPage);
    }

    public function create(CityData $data) : City{
        $dataArray = ['name' => $data->name, 'state_id' => $data->state_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $city = City::create($cleanData);

        $city->refresh();

        return $city;
    }

    public function update(City $city, CityData $data) : City{
        $dataArray = ['name' => $data->name, 'state_id' => $data->state_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null; 
        });

        $city->update($cleanData);

        return $city;
    }

    public function delete(City $city) : bool{
        return $city->delete();
    }

}