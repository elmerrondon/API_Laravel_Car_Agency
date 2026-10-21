<?php

use App\Enums\Users\PermissionEnum;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\User;

function permissionModel(PermissionEnum $permission): Permission
{
    return Permission::create([
        'name' => $permission->value,
        'description' => $permission->description(),
    ]);
}

it('concede un permiso cuando algún rol lo incluye', function () {
    $role = Role::create(['name' => 'Tester', 'description' => 'Rol de prueba']);
    $role->permissions()->sync([permissionModel(PermissionEnum::VIEW_CARS)->id]);

    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    expect($user->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::DELETE_CARS))->toBeFalse();
});

it('acumula los permisos de varios roles', function () {
    $sales = Role::create(['name' => 'Ventas', 'description' => 'Rol de ventas']);
    $sales->permissions()->sync([permissionModel(PermissionEnum::CREATE_SALES)->id]);

    $catalogs = Role::create(['name' => 'Catálogos', 'description' => 'Rol de catálogos']);
    $catalogs->permissions()->sync([permissionModel(PermissionEnum::VIEW_CATALOGS)->id]);

    $user = User::factory()->create();
    $user->roles()->sync([$sales->id, $catalogs->id]);

    expect($user->hasPermissionTo(PermissionEnum::CREATE_SALES))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::VIEW_CATALOGS))->toBeTrue();
});

it('niega los permisos a un usuario sin roles', function () {
    $user = User::factory()->create();

    expect($user->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeFalse();
});
