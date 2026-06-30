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
    public function index(IndexCityRequest $request, CityService $service){
        $perPage = $request->validated('per_page');
        $cities = $service->getAllPaginated($perPage);

        return CityResource::collection($cities);
    }

    public function show(City $city){
        return new CityResource($city);
    }

    public function store(StoreCityRequest $request, CityService $service){
        $dto = CityData::fromRequest($request);

        $city = $service->create($dto);

        return new CityResource($city);
    }

    public function update(UpdateCityRequest $request, City $city, CityService $service){
        $dto = CityData::fromRequest($request);

        $UpdatedCity = $service->update($city, $dto);

        return new CityResource($UpdatedCity);
    }

    public function destroy(City $city, CityService $service){
        $service->delete($city);

        return response()->noContent();
    }

}
