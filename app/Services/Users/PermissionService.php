<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Users\Permission;

class PermissionService
{
    public function getAllPermission()
    {
        return Permission::all();
    }
}
