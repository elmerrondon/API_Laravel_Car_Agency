<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;
use Illuminate\Support\Facades\Cache;

it('rechaza el login de un usuario inactivo', function () {
    $user = User::factory()->create([
        'email' => 'inactive@example.test',
        'is_active' => false,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertUnauthorized();
});

it('limita los intentos de login por email e IP', function () {
    Cache::flush();

    $payload = [
        'email' => 'throttle@example.test',
        'password' => 'wrong-password',
    ];

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', $payload)->assertStatus(429);
});

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->roles()->sync([
        Role::where('name', RoleEnum::ADMIN->value)->value('id'),
    ]);
    $this->adminToken = $this->admin->createToken('admin-test')->plainTextToken;

    $this->target = User::factory()->create();
    $this->targetToken = $this->target->createToken('target-test')->plainTextToken;
});

it('revoca los tokens del usuario al cambiar su contraseña', function () {
    $this->withToken($this->adminToken)
        ->patchJson("/api/users/{$this->target->id}", [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($this->targetToken)
        ->getJson('/api/cars')
        ->assertUnauthorized();
});

it('revoca los tokens del usuario al desactivar la cuenta', function () {
    $this->withToken($this->adminToken)
        ->patchJson("/api/users/{$this->target->id}", [
            'is_active' => false,
        ])
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($this->targetToken)
        ->getJson('/api/cars')
        ->assertUnauthorized();
});

it('revoca los tokens del usuario al eliminarlo', function () {
    $this->withToken($this->adminToken)
        ->deleteJson("/api/users/{$this->target->id}")
        ->assertNoContent();

    $this->app['auth']->forgetGuards();

    $this->withToken($this->targetToken)
        ->getJson('/api/cars')
        ->assertUnauthorized();
});
