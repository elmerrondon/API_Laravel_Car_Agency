<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(fn () => actingAsRole(RoleEnum::ADMIN));

it('rechaza un document_number de más de 10 caracteres al actualizar', function () {
    $user = User::factory()->create();

    $this->patchJson("/api/users/{$user->id}", [
        'document_number' => str_repeat('1', 11),
    ])->assertUnprocessable()->assertJsonValidationErrors('document_number');
});

it('rechaza un code de más de 10 caracteres al actualizar', function () {
    $user = User::factory()->create();

    $this->patchJson("/api/users/{$user->id}", [
        'code' => 'USR-1234567',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('rechaza un document_number de más de 10 caracteres al crear', function () {
    $this->seed(DatabaseSeeder::class);

    $roleId = Role::where('name', RoleEnum::SELLER->value)->value('id');
    $branchId = Branch::firstOrFail()->id;

    $this->postJson('/api/users', [
        'name' => 'Usuario',
        'last_name' => 'Prueba',
        'document_number' => str_repeat('1', 11),
        'code' => 'USR-PRUEBA',
        'email' => 'usuario.prueba@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => [$roleId],
        'branches' => [$branchId],
    ])->assertUnprocessable()->assertJsonValidationErrors('document_number');
});

it('rechaza un code de más de 10 caracteres al crear', function () {
    $this->seed(DatabaseSeeder::class);

    $roleId = Role::where('name', RoleEnum::SELLER->value)->value('id');
    $branchId = Branch::firstOrFail()->id;

    $this->postJson('/api/users', [
        'name' => 'Usuario',
        'last_name' => 'Prueba',
        'document_number' => 'DOC-PRUEBA',
        'code' => 'USR-1234567',
        'email' => 'usuario.code@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => [$roleId],
        'branches' => [$branchId],
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});
