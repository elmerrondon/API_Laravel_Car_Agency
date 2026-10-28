<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\StateData;
use App\Models\Locations\State;

class StateService
{
    private const COUNTRY_RELATION = 'country:id,name,is_active';

    public function getAllPaginated(?int $perPage = null)
    {
        return State::with(self::COUNTRY_RELATION)
            ->select(['id', 'name', 'country_id', 'is_active'])
            ->paginate($perPage);
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

        return $this->getState($state);
    }

    public function updateState(State $state, StateData $data): State
    {
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state->update($cleanData);

        return $this->getState($state);
    }

    public function deleteState(State $state): bool
    {
        return $state->delete();
    }
}
