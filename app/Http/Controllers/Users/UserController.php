<?php

namespace App\Http\Controllers\Users;

use App\DTOs\Users\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\User\IndexUserRequest;
use App\Http\Requests\Users\User\StoreUserRequest;
use App\Http\Requests\Users\User\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\Users\User;
use App\Services\Users\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(IndexUserRequest $request, UserService $service){
        $perPage = $request->validated('per_page');

        $users = $service->getAllPaginated($perPage);

        return UserResource::collection($users);
    }

    public function show(User $user){
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request, UserService $service){
        $dto = UserData::fromRequest($request);

        $user = $service->create($dto);

        return new UserResource($user);
    }

    public function update(User $user, UpdateUserRequest $request, UserService $service){
        $dto = UserData::fromRequest($request);

        $user = $service->update($user, $dto);

        return new UserResource($user);
    }

    public function destroy(User $user, UserService $service){
        $service->delete($user);

        return response()->noContent();
    }
}
