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
    public function __construct(private readonly CategoryService $service)
    {
    
    }

    public function index(IndexCategoryRequest $request){
        $perPage = $request->validated('per_page');
        $categories = $this->service->getAllPaginated($perPage);
        
        return CategoryResource::collection($categories);
    }

    public function show(Category $category){
        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request){
        $dto = CategoryData::fromRequest($request);

        $category = $this->service->createCategory($dto);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category){
        $dto = CategoryData::fromRequest($request);

        $category = $this->service->updateCategory($category,$dto);
        
        return new CategoryResource($category);
    }

    public function destroy(Category $category){
        $this->service->deleteCategory($category);

        return response()->noContent();
    }
}
