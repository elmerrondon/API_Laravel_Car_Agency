<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\User;
use Illuminate\Support\Facades\Hash;

it('permite a un administrador asignar una contraseña temporal', function () {
    actingAsRole(RoleEnum::ADMIN);

    $target = User::factory()->create(['must_change_password' => false]);
    $target->createToken('web');
    $target->createToken('mobile');

    $this->postJson("/api/users/{$target->id}/reset-password", [
        'password' => 'temporal-123',
        'password_confirmation' => 'temporal-123',
    ])
        ->assertOk()
        ->assertExactJson(['message' => 'Password Reset Successfully']);

    $target->refresh();

    expect(Hash::check('temporal-123', $target->password))->toBeTrue()
        ->and($target->must_change_password)->toBeTrue()
        ->and($target->tokens()->count())->toBe(0);
});

it('rechaza el reset a un usuario sin manage_users', function () {
    actingAsRole(RoleEnum::SELLER);

    $target = User::factory()->create();

    $this->postJson("/api/users/{$target->id}/reset-password", [
        'password' => 'temporal-123',
        'password_confirmation' => 'temporal-123',
    ])->assertForbidden();
});

it('valida la contraseña temporal', function () {
    actingAsRole(RoleEnum::ADMIN);

    $target = User::factory()->create();

    $this->postJson("/api/users/{$target->id}/reset-password", [
        'password' => 'temporal-123',
        'password_confirmation' => 'otra-clave-123',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('exige autenticación para resetear una contraseña', function () {
    $target = User::factory()->create();

    $this->postJson("/api/users/{$target->id}/reset-password", [
        'password' => 'temporal-123',
        'password_confirmation' => 'temporal-123',
    ])->assertUnauthorized();
});
