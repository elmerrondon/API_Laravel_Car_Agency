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

it('entrega un token nuevo y revoca el anterior', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/auth/refresh')
        ->assertOk()
        ->assertJsonPath('message', 'Token Refresh Success')
        ->assertJsonStructure(['message', 'token']);

    $newToken = $response->json('token');

    expect($newToken)->toBeString()
        ->not->toBeEmpty()
        ->not->toBe($this->token)
        ->and($this->user->tokens()->pluck('name')->all())->toBe(['api_token']);

    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
    $this->withToken($newToken)->getJson('/api/cars')->assertOk();
});

it('renueva la expiración del token', function () {
    $originalCreatedAt = $this->user->tokens()->firstOrFail()->created_at;

    $this->travel(60)->minutes();

    $newToken = $this->withToken($this->token)
        ->postJson('/api/auth/refresh')
        ->assertOk()
        ->json('token');

    $renewed = $this->user->tokens()->firstOrFail();

    expect($renewed->created_at->greaterThan($originalCreatedAt))->toBeTrue();

    $this->travel(90)->minutes();
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
    $this->withToken($newToken)->getJson('/api/cars')->assertOk();
});

it('exige autenticación para refrescar el token', function () {
    $this->postJson('/api/auth/refresh')->assertUnauthorized();
});
