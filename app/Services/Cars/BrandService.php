<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\BrandData;
use App\Enums\Audit\AuditAction;
use App\Models\Cars\Brand;
use App\Services\Audit\AuditService;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @return LengthAwarePaginator<int, Brand> */
    public function getAllPaginated(?int $perPage = null): LengthAwarePaginator
    {
        return Brand::select(['id', 'name', 'description', 'country_id', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createBrand(BrandData $data): Brand
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'country_id' => $data->countryId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $brand = Brand::create($cleanData);

        $brand->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $brand, [], $this->brandAuditValues($brand));

        return $brand;
    }

    public function updateBrand(Brand $brand, BrandData $data): Brand
    {
        $oldValues = $this->brandAuditValues($brand);

        $arrayData = ['name' => $data->name, 'description' => $data->description, 'country_id' => $data->countryId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $brand->update($cleanData);
        $brand->refresh();

        $newValues = $this->brandAuditValues($brand);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $brand, $oldValues, $newValues);
        }

        return $brand;
    }

    public function deleteBrand(Brand $brand): bool
    {
        $oldValues = $this->brandAuditValues($brand);

        $deleted = $brand->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $brand, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function brandAuditValues(Brand $brand): array
    {
        return [
            'name' => (string) $brand->name,
            'description' => (string) $brand->description,
            'country_id' => (int) $brand->country_id,
            'is_active' => (bool) $brand->is_active,
        ];
    }
}
