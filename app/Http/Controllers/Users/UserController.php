<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\DTOs\Users\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\User\IndexUserRequest;
use App\Http\Requests\Users\User\StoreUserRequest;
use App\Http\Requests\Users\User\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\Users\User;
use App\Services\Users\UserService;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function index(IndexUserRequest $request)
    {
        $perPage = $this->perPage($request);

        $users = $this->service->getAllPaginated($perPage);

        return UserResource::collection($users);
    }

    public function show(User $user)
    {
        return new UserResource($this->service->getUser($user));
    }

    public function store(StoreUserRequest $request)
    {
        $dto = UserData::fromRequest($request);

        $user = $this->service->createUser($dto);

        return new UserResource($user);
    }

    public function update(User $user, UpdateUserRequest $request)
    {
        $dto = UserData::fromRequest($request);

        $user = $this->service->updateUser($user, $dto);

        return new UserResource($user);
    }

    public function destroy(User $user)
    {

        $this->service->deleteUser($user);

        return response()->noContent();
    }
}
