<?php

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\BrandData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Brand\IndexBrandRequest;
use App\Http\Requests\Cars\Brand\StoreBrandRequest;
use App\Http\Requests\Cars\Brand\UpdateBrandRequest;
use App\Http\Resources\Cars\BrandResource;
use App\Models\Cars\Brand;
use App\Services\Cars\BrandService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $service)
    {
        
    }

    public function index(IndexBrandRequest $indexBrand){
        $perPage = $indexBrand->validated('per_page');
        
        $brands = $this->service->getAllPaginated($perPage);

        return BrandResource::collection($brands);
    }

    public function show(Brand $brand){
        return new BrandResource($brand);
    }

    public function store(StoreBrandRequest $request){
        $dto = BrandData::fromRequest($request);

        $brand = $this->service->createBrand($dto);

        return new BrandResource($brand);
    }

    public function update(Brand $brand, UpdateBrandRequest $request){
        $dto = BrandData::fromRequest($request);

        $brand = $this->service->updateBrand($brand, $dto);

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand){
        $this->service->deleteBrand($brand);

        return response()->noContent();
    }
}
