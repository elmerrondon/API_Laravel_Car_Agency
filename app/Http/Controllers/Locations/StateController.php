<?php

declare(strict_types=1);

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\StateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\State\IndexStateRequest;
use App\Http\Requests\Locations\State\StoreStateRequest;
use App\Http\Requests\Locations\State\UpdateStateRequest;
use App\Http\Resources\Locations\StateResource;
use App\Models\Locations\State;
use App\Services\Locations\StateService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StateController extends Controller
{
    public function __construct(private readonly StateService $service) {}

    public function index(IndexStateRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);
        $states = $this->service->getAllPaginated($perPage);

        return StateResource::collection($states);
    }

    public function show(State $state): StateResource
    {
        return new StateResource($this->service->getState($state));
    }

    public function store(StoreStateRequest $request): StateResource
    {
        $dto = StateData::fromRequest($request);

        $state = $this->service->createState($dto);

        return new StateResource($state);
    }

    public function update(UpdateStateRequest $request, State $state): StateResource
    {
        $dto = StateData::fromRequest($request);

        $state = $this->service->updateState($state, $dto);

        return new StateResource($state);
    }

    public function destroy(State $state): Response
    {
        $this->service->deleteState($state);

        return response()->noContent();
    }
}
