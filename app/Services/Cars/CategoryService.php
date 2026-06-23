<?php 

namespace App\Services\Cars;

use App\DTOs\Cars\CategoryData;
use App\Models\Cars\Category;

class CategoryService{
    public function getAllPaginated(int $perPage = 15){
        return Category::paginate($perPage);
    }

    public function create(CategoryData $data){
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $category = Category::create($cleanData);

        $category->refresh();

        return $category;
    }

    public function update(Category $category, CategoryData $data){
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $category->update($cleanData);

        return $category;
    }

    public function delete(Category $category) : bool{
        return $category->delete();
    }
}