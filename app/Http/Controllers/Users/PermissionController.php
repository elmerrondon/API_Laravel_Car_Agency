<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Users\PermissionResource;
use App\Services\Users\PermissionService;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(PermissionService $service){
        $permissions = $service->getAllPermission();
        
        return PermissionResource::collection($permissions);
    }
}
