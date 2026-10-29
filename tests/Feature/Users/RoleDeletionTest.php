<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\User;

beforeEach(fn () => actingAsRole(RoleEnum::ADMIN));

it('no elimina un rol asignado a usuarios', function () {
    $role = Role::create([
        'name' => 'custom-role',
        'description' => 'Rol personalizado de prueba',
    ]);

    $role->permissions()->sync([Permission::firstOrFail()->id]);

    $user = User::factory()->create();
    $user->roles()->sync([$role->id]);

    $this->deleteJson("/api/roles/{$role->id}")
        ->assertStatus(409)
        ->assertJson([
            'message' => 'The role cannot be deleted because it has users assigned.',
        ]);

    expect(Role::find($role->id))->not->toBeNull();
});
