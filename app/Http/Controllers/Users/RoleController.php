<?php

namespace App\Http\Controllers\Users;

use App\DTOs\Users\RoleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\Role\IndexRoleRequest;
use App\Http\Requests\Users\Role\StoreRoleRequest;
use App\Http\Requests\Users\Role\UpdateRoleRequest;
use App\Http\Resources\Users\RoleResource;
use App\Models\Users\Role;
use App\Services\Users\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service)
    {
        
    }

    public function index(IndexRoleRequest $request){
        $perPage = $request->validated('per_page');
        $roles = $this->service->getAllPaginated($perPage);

        return RoleResource::collection($roles);
    }

    public function show(Role $role){
        return new RoleResource($role);
    }

    public function store(StoreRoleRequest $request){
        $dto = RoleData::fromRequest($request);

        $role = $this->service->create($dto);

        return new RoleResource($role);
    }

    public function update(Role $role, UpdateRoleRequest $request){
        $dto = RoleData::fromRequest($request);

        $role = $this->service->update($role,$dto);

        return new RoleResource($role);
    }

    public function destroy(Role $role){
        return $this->service->delete($role);
    }
}
