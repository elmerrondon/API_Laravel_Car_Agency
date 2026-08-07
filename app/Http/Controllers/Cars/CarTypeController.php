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
    public function __construct(private readonly CarTypeService $service)
    {
        
    }

    public function index(IndexCarTypeRequest $request){
        $perPage = $request->validated('per_page');
        $carTypes = $this->service->getAllPaginated($perPage);

        return CarTypeResource::collection($carTypes);
    }

    public function show(CarType $carType){
        return new CarTypeResource($carType);
    }

    public function store(StoreCarTypeRequest $request){
        $dto = CarTypeData::fromRequest($request);

        $carType = $this->service->createCarType($dto);

        return new CarTypeResource($carType);
    }

    public function update(CarType $carType, UpdateCarTypeRequest $request){
        $dto = CarTypeData::fromRequest($request);

        $carType = $this->service->updateCarType($carType, $dto);

        return new CarTypeResource($carType);
    }

    public function destroy(CarType $carType){
        $this->service->deleteCarType($carType);

        return response()->noContent();
    }
}
