<?php

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\CityData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\City\IndexCityRequest;
use App\Http\Requests\Locations\City\StoreCityRequest;
use App\Http\Requests\Locations\City\UpdateCityRequest;
use App\Http\Resources\Locations\CityResource;
use App\Models\Locations\City;
use App\Services\Locations\CityService;
use Illuminate\Http\Request;

class CityController extends Controller
{
    public function __construct(private readonly CityService $service)
    {
        
    }

    public function index(IndexCityRequest $request){
        $perPage = $request->validated('per_page');
        $cities = $this->service->getAllPaginated($perPage);

        return CityResource::collection($cities);
    }

    public function show(City $city){
        return new CityResource($city);
    }

    public function store(StoreCityRequest $request){
        $dto = CityData::fromRequest($request);

        $city = $this->service->createCity($dto);

        return new CityResource($city);
    }

    public function update(UpdateCityRequest $request, City $city){
        $dto = CityData::fromRequest($request);

        $UpdatedCity = $this->service->updateCity($city, $dto);

        return new CityResource($UpdatedCity);
    }

    public function destroy(City $city){
        $this->service->deleteCity($city);

        return response()->noContent();
    }

}
