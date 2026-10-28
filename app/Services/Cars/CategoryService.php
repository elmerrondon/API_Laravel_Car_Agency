<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CategoryData;
use App\Models\Cars\Category;

class CategoryService
{
    public function getAllPaginated(?int $perPage = null)
    {
        return Category::select(['id', 'name', 'description', 'is_active'])->paginate($perPage);
    }

    public function createCategory(CategoryData $data)
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $category = Category::create($cleanData);

        $category->refresh();

        return $category;
    }

    public function updateCategory(Category $category, CategoryData $data)
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $category->update($cleanData);

        return $category;
    }

    public function deleteCategory(Category $category): bool
    {
        return $category->delete();
    }
}
