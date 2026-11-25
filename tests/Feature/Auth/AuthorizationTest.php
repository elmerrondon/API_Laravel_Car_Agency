<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\User;

it('rechaza con 401 una ruta protegida sin token', function () {
    $response = $this->get('/api/cars');

    $response->assertUnauthorized();

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('rechaza con 403 a un usuario sin el permiso requerido', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/users')->assertForbidden();
});

it('permite con 200 a un usuario que tiene el permiso', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/cars')->assertOk();
});

it('mantiene público el login sin token', function () {
    $user = User::factory()->create(['email' => 'login@example.test']);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'token']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});
