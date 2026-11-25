<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->user->roles()->sync([
        Role::where('name', RoleEnum::ADMIN->value)->value('id'),
    ]);

    $this->token = $this->user->createToken('api-token')->plainTextToken;
});

it('cierra la sesión y revoca el token actual', function () {
    $this->withToken($this->token)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logout Success');

    expect($this->user->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
});

it('exige autenticación para cerrar sesión', function () {
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});

it('solo revoca el token que se está usando', function () {
    $otherDeviceToken = $this->user->createToken('other-device')->plainTextToken;

    $this->withToken($this->token)
        ->postJson('/api/auth/logout')
        ->assertOk();

    expect($this->user->tokens()->pluck('name')->all())->toBe(['other-device']);

    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
    $this->withToken($otherDeviceToken)->getJson('/api/cars')->assertOk();
});
