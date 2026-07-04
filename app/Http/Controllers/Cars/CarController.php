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
    public function __construct(private readonly CarService $service)
    {
        
    }

    public function index(IndexCarRequest $request){
        $perPage = $request->validated('per_page');

        $cars = $this->service->getAllPaginated($perPage);

        return CarResource::collection($cars);
    }

    public function show(Car $car){
        return new CarResource($car);
    }

    public function store(StoreCarRequest $request){
        $dto = CarData::fromRequest($request);

        $car = $this->service->createCar($dto);

        return new CarResource($car);
    }

    public function update(Car $car, UpdateCarRequest $request){
        $dto = CarData::fromRequest($request);

        $carU = $this->service->updateCar($car,$dto);

        return new CarResource($carU);
    }

    public function destroy(Car $car){
        $this->service->deleteCar($car);

        return response()->noContent();
    }
}
