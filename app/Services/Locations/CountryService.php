<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\CountryData;
use App\Enums\Audit\AuditAction;
use App\Models\Locations\Country;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class CountryService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{is_active: bool|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, Country>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = Country::select(['id', 'name', 'is_active']);

        if ($filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        ListFilters::search($query, 'name', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createCountry(CountryData $data): Country
    {
        $dataArray = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $country = Country::create($cleanData);

        $country->refresh();

        $this->audit->record(AuditAction::LOCATION_CREATED, $country, [], $this->countryAuditValues($country));

        return $country;
    }

    public function updateCountry(Country $country, CountryData $data): Country
    {
        $oldValues = $this->countryAuditValues($country);

        $dataArray = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $country->update($cleanData);
        $country->refresh();

        $newValues = $this->countryAuditValues($country);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::LOCATION_UPDATED, $country, $oldValues, $newValues);
        }

        return $country;
    }

    public function deleteCountry(Country $country): bool
    {
        $oldValues = $this->countryAuditValues($country);

        $deleted = $country->delete();

        $this->audit->record(AuditAction::LOCATION_DELETED, $country, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function countryAuditValues(Country $country): array
    {
        return [
            'name' => (string) $country->name,
            'is_active' => (bool) $country->is_active,
        ];
    }
}
