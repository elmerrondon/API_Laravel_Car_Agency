<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\BrandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Brand\IndexBrandRequest;
use App\Http\Requests\Cars\Brand\StoreBrandRequest;
use App\Http\Requests\Cars\Brand\UpdateBrandRequest;
use App\Http\Resources\Cars\BrandResource;
use App\Models\Cars\Brand;
use App\Services\Cars\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $service) {}

    public function index(IndexBrandRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $brands = $this->service->getAllPaginated($this->filters($request), $perPage);

        return BrandResource::collection($brands);
    }

    /**
     * @return array{country_id: int|null, is_active: bool|null, search: string|null}
     */
    private function filters(IndexBrandRequest $request): array
    {
        return [
            'country_id' => $this->nullableInt($request, 'country_id'),
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        $dto = BrandData::fromRequest($request);

        $brand = $this->service->createBrand($dto);

        return new BrandResource($brand);
    }

    public function update(Brand $brand, UpdateBrandRequest $request): BrandResource
    {
        $dto = BrandData::fromRequest($request);

        $brand = $this->service->updateBrand($brand, $dto);

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand): Response
    {
        $this->service->deleteBrand($brand);

        return response()->noContent();
    }
}
