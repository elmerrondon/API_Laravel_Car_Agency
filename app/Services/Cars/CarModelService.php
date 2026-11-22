<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CarModelData;
use App\Enums\Audit\AuditAction;
use App\Models\Cars\CarModel;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class CarModelService
{
    private const BRAND_RELATION = 'brand:id,name,description,country_id,is_active';

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{brand_id: int|null, is_active: bool|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, CarModel>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = CarModel::with(self::BRAND_RELATION)
            ->select(['id', 'name', 'description', 'brand_id', 'is_active']);

        if ($filters['brand_id'] !== null) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if ($filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        ListFilters::search($query, 'name', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getCarModel(CarModel $carModel): CarModel
    {
        return $carModel->load(self::BRAND_RELATION);
    }

    public function createCarModel(CarModelData $data): CarModel
    {
        $arrayData = ['name' => $data->name, 'description' => $data->description, 'brand_id' => $data->brandId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $carModel = CarModel::create($cleanData);

        $carModel->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $carModel, [], $this->carModelAuditValues($carModel));

        return $carModel->load(self::BRAND_RELATION);
    }

    public function updateCarModel(CarModel $carModel, CarModelData $data): CarModel
    {
        $oldValues = $this->carModelAuditValues($carModel);

        $arrayData = ['name' => $data->name, 'description' => $data->description, 'brand_id' => $data->brandId, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $carModel->update($cleanData);
        $carModel->refresh();

        $newValues = $this->carModelAuditValues($carModel);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $carModel, $oldValues, $newValues);
        }

        return $carModel->load(self::BRAND_RELATION);
    }

    public function deleteCarModel(CarModel $carModel): bool
    {
        $oldValues = $this->carModelAuditValues($carModel);

        $deleted = $carModel->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $carModel, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function carModelAuditValues(CarModel $carModel): array
    {
        return [
            'name' => (string) $carModel->name,
            'description' => (string) $carModel->description,
            'brand_id' => (int) $carModel->brand_id,
            'is_active' => (bool) $carModel->is_active,
        ];
    }
}
