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
        ->and(config('rate_limits.api'))->toBe(120)
        ->and(config('rate_limits.reports'))->toBe(30);
});
