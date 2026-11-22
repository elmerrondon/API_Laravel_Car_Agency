<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\StateData;
use App\Enums\Audit\AuditAction;
use App\Models\Locations\State;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class StateService
{
    private const COUNTRY_RELATION = 'country:id,name,is_active';

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{country_id: int|null, is_active: bool|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, State>
     */
    public function getAllPaginated(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = State::with(self::COUNTRY_RELATION)
            ->select(['id', 'name', 'country_id', 'is_active']);

        if ($filters['country_id'] !== null) {
            $query->where('country_id', $filters['country_id']);
        }

        if ($filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        ListFilters::search($query, 'name', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getState(State $state): State
    {
        return $state->load(self::COUNTRY_RELATION);
    }

    public function createState(StateData $data): State
    {
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state = State::create($cleanData);

        $state->refresh();

        $this->audit->record(AuditAction::LOCATION_CREATED, $state, [], $this->stateAuditValues($state));

        return $this->getState($state);
    }

    public function updateState(State $state, StateData $data): State
    {
        $oldValues = $this->stateAuditValues($state);

        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state->update($cleanData);
        $state->refresh();

        $newValues = $this->stateAuditValues($state);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::LOCATION_UPDATED, $state, $oldValues, $newValues);
        }

        return $this->getState($state);
    }

    public function deleteState(State $state): bool
    {
        $oldValues = $this->stateAuditValues($state);

        $deleted = $state->delete();

        $this->audit->record(AuditAction::LOCATION_DELETED, $state, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function stateAuditValues(State $state): array
    {
        return [
            'name' => (string) $state->name,
            'country_id' => (int) $state->country_id,
            'is_active' => (bool) $state->is_active,
        ];
    }
}
