<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;
use App\Models\Users\User;

/**
 * Crea un administrador extra. El rol se asigna directamente porque la API
 * exige `manage_users` y aquí solo queremos preparar el escenario.
 */
function createAdmin(bool $active = true): User
{
    $user = User::factory()->create(['is_active' => $active]);
    $user->roles()->sync([Role::where('name', RoleEnum::ADMIN->value)->value('id')]);

    return $user;
}

it('no permite desactivar al último administrador activo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);

    $this->patchJson("/api/users/{$admin->id}", ['is_active' => false])
        ->assertStatus(409);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('no permite borrar al último administrador activo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);

    $this->deleteJson("/api/users/{$admin->id}")->assertStatus(409);

    expect(User::find($admin->id))->not->toBeNull();
});

it('no permite quitar el rol admin al último administrador activo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);
    $managerRoleId = Role::where('name', RoleEnum::MANAGER->value)->value('id');

    $this->patchJson("/api/users/{$admin->id}", ['roles' => [$managerRoleId]])
        ->assertStatus(409);

    expect($admin->fresh()->roles()->pluck('name')->all())->toBe([RoleEnum::ADMIN->value]);
});

it('no cuenta a un administrador inactivo como respaldo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);
    createAdmin(active: false);

    $this->patchJson("/api/users/{$admin->id}", ['is_active' => false])
        ->assertStatus(409);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('permite desactivar al administrador si queda otro activo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);
    createAdmin();

    $this->patchJson("/api/users/{$admin->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($admin->fresh()->is_active)->toBeFalse();
});

it('permite borrar al administrador si queda otro activo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);
    createAdmin();

    $this->deleteJson("/api/users/{$admin->id}")->assertNoContent();

    expect(User::find($admin->id))->toBeNull();
});

it('permite cambiar de rol si queda otro administrador activo', function () {
    $admin = actingAsRole(RoleEnum::ADMIN);
    createAdmin();
    $managerRoleId = Role::where('name', RoleEnum::MANAGER->value)->value('id');

    $this->patchJson("/api/users/{$admin->id}", ['roles' => [$managerRoleId]])
        ->assertOk()
        ->assertJsonPath('data.roles.0.name', RoleEnum::MANAGER->value);
});
