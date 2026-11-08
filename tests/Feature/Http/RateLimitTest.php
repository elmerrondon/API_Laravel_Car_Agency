<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;

it('limita las peticiones generales del API por usuario', function () {
    config(['rate_limits.api' => 2]);

    actingAsRole(RoleEnum::ADMIN);

    $this->getJson('/api/cars')->assertOk();
    $this->getJson('/api/cars')->assertOk();

    $this->getJson('/api/cars')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('limita por IP el tráfico anónimo antes de autenticar', function () {
    config(['rate_limits.api_anon' => 1]);

    $this->getJson('/api/cars')->assertUnauthorized();

    $this->getJson('/api/cars')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('limita por IP los tokens inválidos', function () {
    config(['rate_limits.api_anon' => 1]);

    $this->withToken('token-invalido')->getJson('/api/cars')->assertUnauthorized();

    $this->withToken('token-invalido')->getJson('/api/cars')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('no consume el cupo anónimo cuando el token es válido', function () {
    config([
        'rate_limits.api_anon' => 1,
        'rate_limits.api' => 2,
    ]);

    actingAsRole(RoleEnum::ADMIN);

    $this->getJson('/api/cars')->assertOk();
    $this->getJson('/api/cars')->assertOk();

    $this->getJson('/api/cars')->assertStatus(429);
});

it('aplica un límite más estricto a los reportes', function () {
    config([
        'rate_limits.api' => 10,
        'rate_limits.reports' => 1,
    ]);

    actingAsRole(RoleEnum::ADMIN);

    $this->getJson('/api/reports/cars')->assertOk();
    $this->getJson('/api/reports/cars')->assertStatus(429);
});

it('mantiene el límite de login configurable', function () {
    config(['rate_limits.login' => 2]);

    $payload = [
        'email' => 'rate-limit@example.test',
        'password' => 'wrong-password',
    ];

    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    $this->postJson('/api/auth/login', $payload)->assertStatus(429);
});

it('lee los límites por defecto de la configuración', function () {
    expect(config('rate_limits.login'))->toBe(5)
        ->and(config('rate_limits.api_anon'))->toBe(30)
        ->and(config('rate_limits.api'))->toBe(120)
        ->and(config('rate_limits.reports'))->toBe(30);
});
