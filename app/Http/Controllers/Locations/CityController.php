<?php

declare(strict_types=1);

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\CityData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\City\IndexCityRequest;
use App\Http\Requests\Locations\City\StoreCityRequest;
use App\Http\Requests\Locations\City\UpdateCityRequest;
use App\Http\Resources\Locations\CityResource;
use App\Models\Locations\City;
use App\Services\Locations\CityService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CityController extends Controller
{
    public function __construct(private readonly CityService $service) {}

    public function index(IndexCityRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $cities = $this->service->getAllPaginated($this->filters($request), $perPage);

        return CityResource::collection($cities);
    }

    /**
     * @return array{state_id: int|null, is_active: bool|null, search: string|null}
     */
    private function filters(IndexCityRequest $request): array
    {
        return [
            'state_id' => $this->nullableInt($request, 'state_id'),
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(City $city): CityResource
    {
        return new CityResource($this->service->getCity($city));
    }

    public function store(StoreCityRequest $request): CityResource
    {
        $dto = CityData::fromRequest($request);

        $city = $this->service->createCity($dto);

        return new CityResource($city);
    }

    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $dto = CityData::fromRequest($request);

        $UpdatedCity = $this->service->updateCity($city, $dto);

        return new CityResource($UpdatedCity);
    }

    public function destroy(City $city): Response
    {
        $this->service->deleteCity($city);

        return response()->noContent();
    }
}
