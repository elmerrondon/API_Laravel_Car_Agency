<?php

declare(strict_types=1);

use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('no bloquea nada mientras el enforcement esté desactivado', function () {
    config(['security.enforce_password_change' => false]);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $admin->forceFill(['must_change_password' => true])->save();

    $token = $admin->createToken('api-token')->plainTextToken;

    $this->withToken($token)->getJson('/api/cars')->assertOk();
});

it('bloquea las rutas no permitidas y deja operar al cambiar la contraseña', function () {
    config(['security.enforce_password_change' => true]);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $admin->forceFill(['must_change_password' => true])->save();

    $token = $admin->createToken('api-token')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.must_change_password', true);

    $this->withToken($token)->getJson('/api/cars')->assertForbidden();
    $this->withToken($token)->postJson('/api/auth/refresh')->assertForbidden();

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'Nueva-Clave-123!',
            'password_confirmation' => 'Nueva-Clave-123!',
        ])
        ->assertOk();

    $this->withToken($token)->getJson('/api/cars')->assertOk();
});

it('permite cerrar sesión con una contraseña temporal pendiente', function () {
    config(['security.enforce_password_change' => true]);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $admin->forceFill(['must_change_password' => true])->save();

    $token = $admin->createToken('api-token')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
});

it('no afecta a usuarios que no tienen el flag activo', function () {
    config(['security.enforce_password_change' => true]);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $token = $admin->createToken('api-token')->plainTextToken;

    $this->withToken($token)->getJson('/api/cars')->assertOk();
});
