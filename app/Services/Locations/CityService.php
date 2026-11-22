<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\CityData;
use App\Enums\Audit\AuditAction;
use App\Models\Locations\City;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class CityService
{
    private const RELATIONS = [
        'state:id,name,country_id,is_active',
        'state.country:id,name,is_active',
    ];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{state_id: int|null, is_active: bool|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, City>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = City::with(self::RELATIONS)
            ->select(['id', 'name', 'state_id', 'is_active']);

        if ($filters['state_id'] !== null) {
            $query->where('state_id', $filters['state_id']);
        }

        if ($filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        ListFilters::search($query, 'name', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getCity(City $city): City
    {
        return $city->load(self::RELATIONS);
    }

    public function createCity(CityData $data): City
    {
        $dataArray = ['name' => $data->name, 'state_id' => $data->state_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $city = City::create($cleanData);

        $city->refresh();

        $this->audit->record(AuditAction::LOCATION_CREATED, $city, [], $this->cityAuditValues($city));

        return $this->getCity($city);
    }

    public function updateCity(City $city, CityData $data): City
    {
        $oldValues = $this->cityAuditValues($city);

        $dataArray = ['name' => $data->name, 'state_id' => $data->state_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $city->update($cleanData);
        $city->refresh();

        $newValues = $this->cityAuditValues($city);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::LOCATION_UPDATED, $city, $oldValues, $newValues);
        }

        return $this->getCity($city);
    }

    public function deleteCity(City $city): bool
    {
        $oldValues = $this->cityAuditValues($city);

        $deleted = $city->delete();

        $this->audit->record(AuditAction::LOCATION_DELETED, $city, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function cityAuditValues(City $city): array
    {
        return [
            'name' => (string) $city->name,
            'state_id' => (int) $city->state_id,
            'is_active' => (bool) $city->is_active,
        ];
    }
}
