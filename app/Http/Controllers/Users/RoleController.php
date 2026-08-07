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
use LogicException;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service)
    {
        
    }

    public function index(IndexRoleRequest $request){
        $perPage = $request->validated('per_page');
        $roles = $this->service->getAllPaginatedRoles($perPage);

        return RoleResource::collection($roles);
    }

    public function show(Role $role){
        $role->load('permissions');
        return new RoleResource($role);
    }

    public function store(StoreRoleRequest $request){
        try{
            $dto = RoleData::fromRequest($request);

            $role = $this->service->createRole($dto);
            
            return new RoleResource($role);

        }catch(LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
     
    }

    public function update(Role $role, UpdateRoleRequest $request){
        try {
            $dto = RoleData::fromRequest($request);

            $role = $this->service->updateRole($role,$dto);
            
            return new RoleResource($role);

        }catch(LogicException $e){
            return response()->json(['message' => $e->getMessage()], 403);
        }
        
    }

    public function destroy(Role $role){
        try{ 
            $response = $this->service->deleteRole($role);
            
            return response()->noContent();
            
        }catch(LogicException $e){
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
