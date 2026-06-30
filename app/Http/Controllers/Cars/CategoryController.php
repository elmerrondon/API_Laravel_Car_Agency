<?php

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Category\IndexCategoryRequest;
use App\Http\Requests\Cars\Category\StoreCategoryRequest;
use App\Http\Requests\Cars\Category\UpdateCategoryRequest;
use App\Http\Resources\Cars\CategoryResource;
use App\Models\Cars\Category;
use App\Services\Cars\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(IndexCategoryRequest $request, CategoryService $service){
        $perPage = $request->validated('per_page');
        $categories = $service->getAllPaginated($perPage);
        
        return CategoryResource::collection($categories);
    }

    public function show(Category $category){
        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request, CategoryService $service){
        $dto = CategoryData::fromRequest($request);

        $category = $service->create($dto);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category, CategoryService $service){
        $dto = CategoryData::fromRequest($request);

        $category = $service->update($category,$dto);
        
        return new CategoryResource($category);
    }

    public function destroy(Category $category, CategoryService $service){
        $service->delete($category);

        return response()->noContent();
    }
}
