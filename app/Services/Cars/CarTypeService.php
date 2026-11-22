<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\CarTypeData;
use App\Enums\Audit\AuditAction;
use App\Models\Cars\CarType;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class CarTypeService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{is_active: bool|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, CarType>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = CarType::select(['id', 'name', 'description', 'is_active']);

        if ($filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        ListFilters::search($query, 'name', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createCarType(CarTypeData $data): CarType
    {
        $dataArray = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $carType = CarType::create($cleanData);

        $carType->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $carType, [], $this->carTypeAuditValues($carType));

        return $carType;
    }

    public function updateCarType(CarType $carType, CarTypeData $data): CarType
    {
        $oldValues = $this->carTypeAuditValues($carType);

        $dataArray = ['name' => $data->name, 'description' => $data->description, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $carType->update($cleanData);
        $carType->refresh();

        $newValues = $this->carTypeAuditValues($carType);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $carType, $oldValues, $newValues);
        }

        return $carType;
    }

    public function deleteCarType(CarType $carType): bool
    {
        $oldValues = $this->carTypeAuditValues($carType);

        $deleted = $carType->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $carType, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function carTypeAuditValues(CarType $carType): array
    {
        return [
            'name' => (string) $carType->name,
            'description' => (string) $carType->description,
            'is_active' => (bool) $carType->is_active,
        ];
    }
}
