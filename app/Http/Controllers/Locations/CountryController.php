<?php

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\CountryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\Country\StoreCountryRequest;
use App\Http\Requests\Locations\Country\UpdateCountryRequest;
use App\Http\Resources\Locations\CountryResource;
use App\Models\Locations\Country;
use App\Services\Locations\CountryService;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(CountryService $service){
        $countries = $service->getAllPaginated();

        return CountryResource::collection($countries);
    }

    public function store(StoreCountryRequest $request, CountryService $service){
        $dto = CountryData::fromRequest($request);

        $country = $service->create($dto);

        return new CountryResource($country);
    }

    public function show(Country $country){
        return new CountryResource($country);
    }

    public function update(UpdateCountryRequest $request, Country $country, CountryService $service){
        $dto = CountryData::fromRequest($request);

        $UpdateCountry = $service->update($country, $dto);

        return new CountryResource($UpdateCountry);
    }

    public function destroy(Country $country, CountryService $service){
        $service->delete($country);

        return response()->json(204);
    }

}
