<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTOs\Users\RoleData;
use App\Enums\Users\RoleEnum;
use App\Exceptions\Users\RoleInUseException;
use App\Models\Users\Role;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class RoleService
{
    public function getAllPaginatedRoles(?int $perPage = null)
    {
        return Role::with('permissions')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createRole(RoleData $data)
    {

        return DB::transaction(function () use ($data) {
            $systemRoles = array_column(RoleEnum::cases(), 'value');

            if (in_array($data->name, $systemRoles)) {
                throw new LogicException('The Role cannot be created because it already exists and is a base system role');
            }

            $arrayData = ['name' => $data->name, 'description' => $data->description];

            $role = Role::create($arrayData);

            if (! empty($data->permissions)) {
                $role->permissions()->sync($data->permissions);
            }

            return $role->load('permissions');
        });

    }

    public function updateRole(Role $role, RoleData $data)
    {

        return DB::transaction(function () use ($role, $data) {
            $systemRoles = array_column(RoleEnum::cases(), 'value');

            if (in_array($role->name, $systemRoles)) {
                throw new LogicException('The role cannot be updated because it is a base system role');
            }

            $arrayData = ['name' => $data->name, 'description' => $data->description];

            $cleanData = array_filter($arrayData, function ($value) {
                return $value !== null;
            });

            $role->update($cleanData);

            if ($data->permissions !== null) {
                $role->permissions()->sync($data->permissions);
            }

            // La matriz del rol cambió: invalidar a sus usuarios.
            User::flushPermissionCacheForRole((int) $role->getKey());

            return $role->load('permissions');
        });

    }

    public function deleteRole(Role $role): bool
    {
        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if (in_array($role->name, $systemRoles)) {
            throw new LogicException('The role cannot be deleted because it is a base system role');
        }

        if ($role->users()->exists()) {
            throw RoleInUseException::assignedToUsers();
        }

        return $role->delete();
    }
}
