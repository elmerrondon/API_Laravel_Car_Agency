<?php

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\CarModelData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\CarModel\IndexCarModelRequest;
use App\Http\Requests\Cars\CarModel\StoreCarModelRequest;
use App\Http\Requests\Cars\CarModel\UpdateCarModelRequest;
use App\Http\Resources\Cars\CarModelResource;
use App\Models\Cars\CarModel;
use App\Services\Cars\CarModelService;
use Illuminate\Http\Request;

class CarModelController extends Controller
{
    public function __construct(private readonly CarModelService $service)
    {
    
    }

    public function index(IndexCarModelRequest $request){
        $perPage = $request->validated('per_page');

        $carModels = $this->service->getAllPaginated($perPage);

        return CarModelResource::collection($carModels);
    }

    public function show(CarModel $carModel){
        return new CarModelResource($carModel);
    }

    public function store(StoreCarModelRequest $request){
        $dto = CarModelData::fromRequest($request);

        $carModel = $this->service->createCarModel($dto);

        return new CarModelResource($carModel);
    }

    public function update(CarModel $carModel, UpdateCarModelRequest $request){
        $dto = CarModelData::fromRequest($request);

        $carModel = $this->service->updateCarModel($carModel,$dto);

        return new CarModelResource($carModel);
    }

    public function destroy(CarModel $carModel){
        $this->service->deleteCarModel($carModel);

        return response()->noContent();
    }
}
