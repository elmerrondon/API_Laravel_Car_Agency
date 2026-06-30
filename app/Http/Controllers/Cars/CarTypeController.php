<?php

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\CarTypeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\CarType\IndexCarTypeRequest;
use App\Http\Requests\Cars\CarType\StoreCarTypeRequest;
use App\Http\Requests\Cars\CarType\UpdateCarTypeRequest;
use App\Http\Resources\Cars\CarTypeResource;
use App\Models\Cars\CarType;
use App\Services\Cars\CarTypeService;
use Illuminate\Http\Request;

class CarTypeController extends Controller
{
    public function index(IndexCarTypeRequest $request, CarTypeService $service){
        $perPage = $request->validated('per_page');
        $carTypes = $service->getAllPaginated($perPage);

        return CarTypeResource::collection($carTypes);
    }

    public function show(CarType $carType){
        return new CarTypeResource($carType);
    }

    public function store(StoreCarTypeRequest $request, CarTypeService $service){
        $dto = CarTypeData::fromRequest($request);

        $carType = $service->create($dto);

        return new CarTypeResource($carType);
    }

    public function update(CarType $carType, UpdateCarTypeRequest $request, CarTypeService $service){
        $dto = CarTypeData::fromRequest($request);

        $carType = $service->update($carType, $dto);

        return new CarTypeResource($carType);
    }

    public function destroy(CarType $carType, CarTypeService $service){
        $service->delete($carType);

        return response()->noContent();
    }
}
