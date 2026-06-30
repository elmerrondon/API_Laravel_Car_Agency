<?php

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\CarData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Car\IndexCarRequest;
use App\Http\Requests\Cars\Car\StoreCarRequest;
use App\Http\Requests\Cars\Car\UpdateCarRequest;
use App\Http\Resources\Cars\CarResource;
use App\Models\Cars\Car;
use App\Services\Cars\CarService;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function index(IndexCarRequest $request, CarService $service){
        $perPage = $request->validated('per_page');

        $cars = $service->getAllPaginated($perPage);

        return CarResource::collection($cars);
    }

    public function show(Car $car){
        return new CarResource($car);
    }

    public function store(StoreCarRequest $request, CarService $service){
        $dto = CarData::fromRequest($request);

        $car = $service->create($dto);

        return new CarResource($car);
    }

    public function update(Car $car, UpdateCarRequest $request, CarService $service){
        $dto = CarData::fromRequest($request);

        $carU = $service->update($car,$dto);

        return new CarResource($carU);
    }

    public function destroy(Car $car, CarService $service){
        $service->delete($car);

        return response()->noContent();
    }
}
