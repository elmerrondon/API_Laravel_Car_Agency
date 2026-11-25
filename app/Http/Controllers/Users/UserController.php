<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\DTOs\Users\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\User\IndexUserRequest;
use App\Http\Requests\Users\User\ResetUserPasswordRequest;
use App\Http\Requests\Users\User\StoreUserRequest;
use App\Http\Requests\Users\User\UpdateUserRequest;
use App\Http\Resources\Users\UserResource;
use App\Models\Users\User;
use App\Services\Users\UserPasswordService;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $service,
        private readonly UserPasswordService $passwordService,
    ) {}

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $users = $this->service->getAllPaginated($this->filters($request), $perPage);

        return UserResource::collection($users);
    }

    /**
     * @return array{role_id: int|null, branch_id: int|null, is_active: bool|null, search: string|null}
     */
    private function filters(IndexUserRequest $request): array
    {
        return [
            'role_id' => $this->nullableInt($request, 'role_id'),
            'branch_id' => $this->nullableInt($request, 'branch_id'),
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(User $user): UserResource
    {
        return new UserResource($this->service->getUser($user));
    }

    public function store(StoreUserRequest $request): UserResource
    {
        $dto = UserData::fromRequest($request);

        $user = $this->service->createUser($dto);

        return new UserResource($user);
    }

    public function update(User $user, UpdateUserRequest $request): UserResource
    {
        $dto = UserData::fromRequest($request);

        $user = $this->service->updateUser($user, $dto);

        return new UserResource($user);
    }

    public function destroy(User $user): Response
    {

        $this->service->deleteUser($user);

        return response()->noContent();
    }

    public function resetPassword(User $user, ResetUserPasswordRequest $request): JsonResponse
    {
        $this->passwordService->resetPassword($user, (string) $request->validated('password'));

        return response()->json(['message' => 'Password Reset Successfully']);
    }
}
