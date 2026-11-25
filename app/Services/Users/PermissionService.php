<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\Users\Permission;
use Illuminate\Database\Eloquent\Collection;

class PermissionService
{
    /**
     * @return Collection<int, Permission>
     */
    public function getAllPermission(): Collection
    {
        return Permission::all();
    }
}
