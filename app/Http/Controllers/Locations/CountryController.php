<?php

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\CountryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\Country\IndexCountryRequest;
use App\Http\Requests\Locations\Country\StoreCountryRequest;
use App\Http\Requests\Locations\Country\UpdateCountryRequest;
use App\Http\Resources\Locations\CountryResource;
use App\Models\Locations\Country;
use App\Services\Locations\CountryService;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function __construct(private readonly CountryService $service)
    {
        
    }

    public function index(IndexCountryRequest $request){
        $perPage = $request->validated('per_page');
        $countries = $this->service->getAllPaginated($perPage);

        return CountryResource::collection($countries);
    }



    public function store(StoreCountryRequest $request){
        $dto = CountryData::fromRequest($request);

        $country = $this->service->createCountry($dto);

        return new CountryResource($country);
    }

    public function show(Country $country){
        return new CountryResource($country);
    }



    public function update(UpdateCountryRequest $request, Country $country){
        $dto = CountryData::fromRequest($request);

        $UpdateCountry = $this->service->updateCountry($country, $dto);

        return new CountryResource($UpdateCountry);
    }



    public function destroy(Country $country){
        $this->service->deleteCountry($country);

        return response()->noContent();
    }

}
