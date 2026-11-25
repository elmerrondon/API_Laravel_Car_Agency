<?php

declare(strict_types=1);

use App\Models\Users\User;
use Illuminate\Support\Facades\Hash;

it('rechaza el cambio si la contraseña actual no coincide', function () {
    $user = User::factory()->create();
    $token = $user->createToken('current')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'incorrecta',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('cambia la contraseña, limpia el flag y conserva solo el token actual', function () {
    $user = User::factory()->create(['must_change_password' => true]);
    $currentToken = $user->createToken('current')->plainTextToken;
    $otherToken = $user->createToken('other-device')->plainTextToken;

    $this->withToken($currentToken)
        ->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])
        ->assertOk()
        ->assertExactJson(['message' => 'Password Updated']);

    $user->refresh();

    expect(Hash::check('nueva-clave-123', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->firstOrFail()->name)->toBe('current');

    $this->app['auth']->forgetGuards();

    $this->withToken($currentToken)->getJson('/api/auth/me')->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($otherToken)->getJson('/api/auth/me')->assertUnauthorized();
});

it('valida la fuerza y la confirmación de la nueva contraseña', function () {
    $user = User::factory()->create();
    $token = $user->createToken('current')->plainTextToken;

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'otra-clave-123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('exige autenticación para cambiar la contraseña', function () {
    $this->putJson('/api/auth/password', [
        'current_password' => 'password',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ])->assertUnauthorized();
});
