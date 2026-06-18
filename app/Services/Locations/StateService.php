<?php 

namespace App\Services\Locations;

use App\DTOs\Locations\StateData;
use App\Models\Locations\State;

class StateService{
    public function getAllPaginated(int $perPage = 15){
        return State::paginate($perPage);
    }

    public function getById(int $id){
        return State::findOrFail($id);
    }

    public function create(StateData $data) : State{
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state = State::create($cleanData);

        $state->refresh();

        return $state;
    }

    public function update(State $state, StateData $data) : State{
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id, 'is_active' => $data->isActive];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state->update($cleanData);

        return $state;
    }

    public function delete(State $state) : bool{
        return $state->delete();
    }

}