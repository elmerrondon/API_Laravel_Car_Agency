<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\User;

beforeEach(fn () => actingAsRole(RoleEnum::ADMIN));

it('rechaza cambiar el código de un usuario', function () {
    $user = User::factory()->create(['code' => 'USR-111']);

    $this->patchJson("/api/users/{$user->id}", ['code' => 'USR-222'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    expect($user->fresh()->code)->toBe('USR-111');
});

it('permite reenviar el código actual sin modificarlo', function () {
    $user = User::factory()->create(['code' => 'USR-111']);

    $this->patchJson("/api/users/{$user->id}", ['code' => 'USR-111'])
        ->assertOk()
        ->assertJsonPath('data.code', 'USR-111');

    expect($user->fresh()->code)->toBe('USR-111');
});
