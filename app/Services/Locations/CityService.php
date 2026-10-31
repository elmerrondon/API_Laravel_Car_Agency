<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\CityData;
use App\Models\Locations\City;

class CityService
{
    private const RELATIONS = [
        'state:id,name,country_id,is_active',
        'state.country:id,name,is_active',
    ];

    public function getAllPaginated(?int $perPage = null)
    {
        return City::with(self::RELATIONS)
            ->select(['id', 'name', 'state_id', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getCity(City $city): City
    {
        return $city->load(self::RELATIONS);
    }

    public function createCity(CityData $data): City
    {
        $dataArray = ['name' => $data->name, 'state_id' => $data->state_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $city = City::create($cleanData);

        $city->refresh();

        return $this->getCity($city);
    }

    public function updateCity(City $city, CityData $data): City
    {
        $dataArray = ['name' => $data->name, 'state_id' => $data->state_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $city->update($cleanData);

        return $this->getCity($city);
    }

    public function deleteCity(City $city): bool
    {
        return $city->delete();
    }
}
