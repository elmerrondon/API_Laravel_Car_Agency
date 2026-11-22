<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\CarModelData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\CarModel\IndexCarModelRequest;
use App\Http\Requests\Cars\CarModel\StoreCarModelRequest;
use App\Http\Requests\Cars\CarModel\UpdateCarModelRequest;
use App\Http\Resources\Cars\CarModelResource;
use App\Models\Cars\CarModel;
use App\Services\Cars\CarModelService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CarModelController extends Controller
{
    public function __construct(private readonly CarModelService $service) {}

    public function index(IndexCarModelRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $carModels = $this->service->getAllPaginated($this->filters($request), $perPage);

        return CarModelResource::collection($carModels);
    }

    /**
     * @return array{brand_id: int|null, is_active: bool|null, search: string|null}
     */
    private function filters(IndexCarModelRequest $request): array
    {
        return [
            'brand_id' => $this->nullableInt($request, 'brand_id'),
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(CarModel $carModel): CarModelResource
    {
        return new CarModelResource($this->service->getCarModel($carModel));
    }

    public function store(StoreCarModelRequest $request): CarModelResource
    {
        $dto = CarModelData::fromRequest($request);

        $carModel = $this->service->createCarModel($dto);

        return new CarModelResource($carModel);
    }

    public function update(CarModel $carModel, UpdateCarModelRequest $request): CarModelResource
    {
        $dto = CarModelData::fromRequest($request);

        $carModel = $this->service->updateCarModel($carModel, $dto);

        return new CarModelResource($carModel);
    }

    public function destroy(CarModel $carModel): Response
    {
        $this->service->deleteCarModel($carModel);

        return response()->noContent();
    }
}
