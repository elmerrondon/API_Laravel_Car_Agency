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
    public function __construct(private readonly UserService $service)
    {
        
    }
    
    public function index(IndexUserRequest $request){
        $perPage = $request->validated('per_page');

        $users = $this->service->getAllPaginated($perPage);

        return UserResource::collection($users);
    }

    public function show(User $user){
        return new UserResource($user);
    }

    public function store(StoreUserRequest $request){
        $dto = UserData::fromRequest($request);

        $user = $this->service->createUser($dto);

        return new UserResource($user);
    }

    public function update(User $user, UpdateUserRequest $request){
        $dto = UserData::fromRequest($request);

        $user = $this->service->updateUser($user, $dto);

        return new UserResource($user);
    }

    public function destroy(User $user){
        
        $this->service->deleteUser($user);

        return response()->noContent();
    }
}
