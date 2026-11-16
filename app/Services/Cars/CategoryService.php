<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CategoryData;
use App\Enums\Audit\AuditAction;
use App\Models\Cars\Category;
use App\Services\Audit\AuditService;

class CategoryService
{
    public function __construct(private readonly AuditService $audit) {}

    public function getAllPaginated(?int $perPage = null)
    {
        return Category::select(['id', 'name', 'description', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createCategory(CategoryData $data): Category
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $category = Category::create($cleanData);

        $category->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $category, [], $this->categoryAuditValues($category));

        return $category;
    }

    public function updateCategory(Category $category, CategoryData $data): Category
    {
        $oldValues = $this->categoryAuditValues($category);

        $arrayData = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $category->update($cleanData);
        $category->refresh();

        $newValues = $this->categoryAuditValues($category);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $category, $oldValues, $newValues);
        }

        return $category;
    }

    public function deleteCategory(Category $category): bool
    {
        $oldValues = $this->categoryAuditValues($category);

        $deleted = $category->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $category, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryAuditValues(Category $category): array
    {
        return [
            'name' => (string) $category->name,
            'description' => (string) $category->description,
            'is_active' => (bool) $category->is_active,
        ];
    }
}
