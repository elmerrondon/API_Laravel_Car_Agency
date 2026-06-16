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
        return State::create(['name' => $data->name, 'country_id' => $data->country_id]);
    }

    public function update(State $state, StateData $data) : State{
        $dataArray = ['name' => $data->name, 'country_id' => $data->country_id];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $state->update($cleanData);

        return $state;
    }

    public function delete(State $state) : bool{
        return $state->delete();
    }

    public function restore(int $id) : State{
        $state = State::withTrashed()->findOrFail($id);

        $state->restore();

        return $state;
    }
}