<?php

declare(strict_types=1);

use App\Models\Users\User;

it('expone must_change_password en el login sin bloquearlo', function () {
    $user = User::factory()->create([
        'email' => 'temporal@example.test',
        'must_change_password' => true,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('must_change_password', true)
        ->assertJsonStructure(['message', 'token', 'must_change_password']);
});

it('devuelve must_change_password en false para una cuenta normal', function () {
    $user = User::factory()->create(['email' => 'normal@example.test']);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('must_change_password', false);
});
