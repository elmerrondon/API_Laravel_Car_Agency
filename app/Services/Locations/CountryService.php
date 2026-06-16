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
        return Country::create(['name' => $data->name]);
    }

    public function update(Country $country, CountryData $data) : Country{
        $country->update(['name' => $data->name]);

        return $country;
    }

    public function delete(Country $country) : bool{
        return $country->delete();
    }

    public function restore(int $id) : Country{
       $country = Country::withTrashed()->findOrFail($id);
       
       $country->restore();

       return $country;
    }
}