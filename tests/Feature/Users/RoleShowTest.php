<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Permission;
use App\Models\Users\Role;

beforeEach(fn () => actingAsRole(RoleEnum::ADMIN));

it('devuelve el rol con sus permisos desde el servicio', function () {
    $role = Role::create([
        'name' => 'rol-consulta',
        'description' => 'Rol para probar el show',
    ]);
    $permission = Permission::firstOrFail();
    $role->permissions()->sync([$permission->id]);

    $response = $this->getJson("/api/roles/{$role->id}")->assertOk();

    expect($response->json('data.permissions'))->toHaveCount(1)
        ->and($response->json('data.permissions.0.id'))->toBe($permission->id);
});
