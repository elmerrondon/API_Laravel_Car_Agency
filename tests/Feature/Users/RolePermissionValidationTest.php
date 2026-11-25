<?php

declare(strict_types=1);

use App\DTOs\Users\RoleData;
use App\Enums\Users\RoleEnum;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Services\Users\RoleService;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => actingAsRole(RoleEnum::ADMIN));

it('rechaza dejar un rol sin permisos al actualizarlo', function () {
    $role = Role::create([
        'name' => 'custom-role',
        'description' => 'Rol personalizado de prueba',
    ]);
    $role->permissions()->sync([Permission::firstOrFail()->id]);

    $this->patchJson("/api/roles/{$role->id}", ['permissions' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions');

    expect($role->permissions()->count())->toBe(1);
});

it('rechaza crear un rol sin permisos', function () {
    $this->postJson('/api/roles', [
        'name' => 'rol-vacio',
        'description' => 'No debería crearse',
        'permissions' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
});

it('defiende la regla en el servicio aunque no pase por el FormRequest', function () {
    $service = app(RoleService::class);

    expect(fn () => $service->createRole(new RoleData(
        name: 'rol-directo',
        description: 'Sin permisos',
        permissions: [],
    )))->toThrow(ValidationException::class);
});
