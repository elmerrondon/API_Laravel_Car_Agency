<?php

declare(strict_types=1);

use App\Enums\Users\PermissionEnum;
use App\Enums\Users\RoleEnum;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

it('devuelve el perfil completo del usuario autenticado', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'last_name',
                'document_number',
                'code',
                'email',
                'is_active',
                'must_change_password',
                'roles',
                'branches',
                'permissions',
            ],
        ]);

    /** @var list<string> $permissions */
    $permissions = $response->json('data.permissions');

    expect($response->json('data.id'))->toBe($admin->id)
        ->and($response->json('data.email'))->toBe('admin@caragency.test')
        ->and($response->json('data.must_change_password'))->toBeFalse()
        ->and($response->json('data.roles.0.name'))->toBe(RoleEnum::ADMIN->value)
        ->and($response->json('data.branches'))->toHaveCount(1)
        ->and($permissions)->toContain(PermissionEnum::VIEW_CARS->value)
        ->and($permissions)->toContain(PermissionEnum::MANAGE_USERS->value)
        ->and($permissions)->toBe(collect($permissions)->sort()->values()->all());
});

it('expone solo los permisos efectivos del rol del usuario', function () {
    actingAsRole(RoleEnum::SELLER);

    $permissions = $this->getJson('/api/auth/me')
        ->assertOk()
        ->json('data.permissions');

    expect($permissions)->toContain(PermissionEnum::VIEW_SALES->value)
        ->not->toContain(PermissionEnum::MANAGE_USERS->value)
        ->not->toContain(PermissionEnum::CANCEL_SALES->value);
});

it('exige autenticación para consultar el perfil', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});
