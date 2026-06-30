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
    public function index(IndexBrandRequest $indexBrand, BrandService $service){
        $perPage = $indexBrand->validated('per_page');
        
        $brands = $service->getAllPaginated($perPage);

        return BrandResource::collection($brands);
    }

    public function show(Brand $brand){
        return new BrandResource($brand);
    }

    public function store(StoreBrandRequest $request, BrandService $service){
        $dto = BrandData::fromRequest($request);

        $brand = $service->create($dto);

        return new BrandResource($brand);
    }

    public function update(Brand $brand, UpdateBrandRequest $request, BrandService $service){
        $dto = BrandData::fromRequest($request);

        $brand = $service->update($brand, $dto);

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand, BrandService $service){
        $service->delete($brand);

        return response()->noContent();
    }
}
