<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;
use Laravel\Sanctum\PersonalAccessToken;

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

it('desactiva por defecto la escritura de last_used_at', function () {
    expect(config('sanctum.last_used_at'))->toBeFalse();

    $this->withToken($this->token)->getJson('/api/auth/me')->assertOk();

    $accessToken = PersonalAccessToken::query()->firstOrFail();

    expect($accessToken->last_used_at)->toBeNull();
});

it('escribe last_used_at cuando la opción está activa', function () {
    config(['sanctum.last_used_at' => true]);
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/auth/me')->assertOk();

    $accessToken = PersonalAccessToken::query()->firstOrFail();

    expect($accessToken->last_used_at)->not->toBeNull();
});
