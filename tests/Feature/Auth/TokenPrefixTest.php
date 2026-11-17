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
});

it('emite tokens nuevos con el prefijo configurado', function () {
    expect(config('sanctum.token_prefix'))->toBe('car_');

    $token = $this->user->createToken('api-token')->plainTextToken;

    expect($token)->toContain('|car_');

    $this->withToken($token)->getJson('/api/auth/me')->assertOk();
});

it('sigue aceptando tokens emitidos antes del prefijo', function () {
    config(['sanctum.token_prefix' => '']);

    $legacyToken = $this->user->createToken('legacy-token')->plainTextToken;

    config(['sanctum.token_prefix' => 'car_']);
    $this->app['auth']->forgetGuards();

    $this->withToken($legacyToken)->getJson('/api/auth/me')->assertOk();
});
