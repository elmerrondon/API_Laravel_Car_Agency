<?php

declare(strict_types=1);

namespace Database\Seeders\Users;

use App\Enums\Users\PermissionEnum;
use App\Enums\Users\RoleEnum;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Matriz de permisos por rol.
     *
     * @return array<string, list<PermissionEnum>>
     */
    public static function matrix(): array
    {
        return [
            RoleEnum::ADMIN->value => PermissionEnum::cases(),
            RoleEnum::MANAGER->value => [
                PermissionEnum::VIEW_CARS,
                PermissionEnum::CREATE_CARS,
                PermissionEnum::UPDATE_CARS,
                PermissionEnum::VIEW_CATALOGS,
                PermissionEnum::MANAGE_CATALOGS,
                PermissionEnum::VIEW_LOCATIONS,
                PermissionEnum::VIEW_SALES,
                PermissionEnum::CREATE_SALES,
                PermissionEnum::CANCEL_SALES,
                PermissionEnum::VIEW_REPORTS,
            ],
            RoleEnum::SELLER->value => [
                PermissionEnum::VIEW_CARS,
                PermissionEnum::VIEW_CATALOGS,
                PermissionEnum::VIEW_LOCATIONS,
                PermissionEnum::VIEW_SALES,
                PermissionEnum::CREATE_SALES,
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::matrix() as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->firstOrFail();

            $permissionIds = Permission::whereIn(
                'name',
                array_map(fn (PermissionEnum $permission) => $permission->value, $permissions)
            )->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
