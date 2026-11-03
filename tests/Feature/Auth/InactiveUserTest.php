<?php

declare(strict_types=1);

use App\Models\Users\User;

it('rechaza el token de un usuario desactivado después de emitirlo', function () {
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('before-deactivation')->plainTextToken;

    $user->update(['is_active' => false]);

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
});

it('rechaza un token emitido para una cuenta ya inactiva', function () {
    $user = User::factory()->create(['is_active' => false]);
    $token = $user->createToken('inactive')->plainTextToken;

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});

it('permite operar a un usuario activo con token vigente', function () {
    $user = User::factory()->create(['is_active' => true]);
    $token = $user->createToken('active')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});
