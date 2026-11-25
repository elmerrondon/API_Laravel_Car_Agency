<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\Users\PermissionResource;
use App\Services\Users\PermissionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    public function __construct(private readonly PermissionService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $permissions = $this->service->getAllPermission();

        return PermissionResource::collection($permissions);
    }
}
