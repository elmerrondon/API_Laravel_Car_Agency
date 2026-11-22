<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\CarData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Car\IndexCarRequest;
use App\Http\Requests\Cars\Car\StoreCarRequest;
use App\Http\Requests\Cars\Car\UpdateCarRequest;
use App\Http\Resources\Cars\CarResource;
use App\Models\Cars\Car;
use App\Services\Cars\CarService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CarController extends Controller
{
    public function __construct(private readonly CarService $service) {}

    public function index(IndexCarRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $cars = $this->service->getAllPaginated($this->filters($request), $perPage);

        return CarResource::collection($cars);
    }

    /**
     * @return array{status: string|null, branch_id: int|null, brand_id: int|null, car_model_id: int|null, car_type_id: int|null, category_id: int|null, color_id: int|null, year_min: int|null, year_max: int|null, price_min: float|null, price_max: float|null, search: string|null}
     */
    private function filters(IndexCarRequest $request): array
    {
        return [
            'status' => $request->validated('status'),
            'branch_id' => $this->nullableInt($request, 'branch_id'),
            'brand_id' => $this->nullableInt($request, 'brand_id'),
            'car_model_id' => $this->nullableInt($request, 'car_model_id'),
            'car_type_id' => $this->nullableInt($request, 'car_type_id'),
            'category_id' => $this->nullableInt($request, 'category_id'),
            'color_id' => $this->nullableInt($request, 'color_id'),
            'year_min' => $this->nullableInt($request, 'year_min'),
            'year_max' => $this->nullableInt($request, 'year_max'),
            'price_min' => $this->nullableFloat($request, 'price_min'),
            'price_max' => $this->nullableFloat($request, 'price_max'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(Car $car): CarResource
    {
        return new CarResource($this->service->getCar($car));
    }

    public function store(StoreCarRequest $request): CarResource
    {
        $dto = CarData::fromRequest($request);

        $car = $this->service->createCar($dto);

        return new CarResource($car);
    }

    public function update(Car $car, UpdateCarRequest $request): CarResource
    {
        $dto = CarData::fromRequest($request);

        $carU = $this->service->updateCar($car, $dto);

        return new CarResource($carU);
    }

    public function destroy(Car $car): Response
    {
        $this->service->deleteCar($car);

        return response()->noContent();
    }
}
