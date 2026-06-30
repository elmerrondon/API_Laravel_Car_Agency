<?php

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\StateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\State\IndexStateRequest;
use App\Http\Requests\Locations\State\StoreStateRequest;
use App\Http\Requests\Locations\State\UpdateStateRequest;
use App\Http\Resources\Locations\StateResource;
use App\Models\Locations\State;
use App\Services\Locations\StateService;
use Illuminate\Http\Request;

class StateController extends Controller
{
    public function index(IndexStateRequest $request, StateService $service){
        $perPage = $request->validated('per_page');
        $states = $service->getAllPaginated($perPage);

        return StateResource::collection($states);
    }

    public function show(State $state){
        return new StateResource($state);
    }

    public function store(StoreStateRequest $request, StateService $service){
        $dto = StateData::fromRequest($request);

        $state = $service->create($dto);

        return new StateResource($state);
    }

    public function update(UpdateStateRequest $request, State $state, StateService $service){
        $dto = StateData::fromRequest($request);

        $state = $service->update($state, $dto);

        return new StateResource($state);
    }

    public function destroy(State $state, StateService $service){
        $service->delete($state);

        return response()->noContent();
    }

}
