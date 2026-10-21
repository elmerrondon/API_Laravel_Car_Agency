<?php

namespace App\Services\Locations;

use App\DTOs\Locations\StateData;
use App\Models\Locations\State;

class StateService
{
    public function getAllPaginated(?int $perPage = null)
    {
        return State::with('country:id,name,is_active')
            ->select(['id', 'name', 'country_id', 'is_active'])
            ->paginate($perPage);
    }

    public function getState(State $state): State
    {
        return $state->load('country:id,name,is_active');
    }

    public function createState(StateData $data): State
    {
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state = State::create($cleanData);

        $state->refresh();

        return $state;
    }

    public function updateState(State $state, StateData $data): State
    {
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state->update($cleanData);

        return $state;
    }

    public function deleteState(State $state): bool
    {
        return $state->delete();
    }
}
