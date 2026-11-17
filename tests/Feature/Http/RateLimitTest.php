<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\RateLimiter;

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

it('no cuenta como anónimo un token válido antes de que la petición tenga user resolver', function () {
    // Reproduce el orden real: `ThrottleAnonymousApi` es middleware global y
    // corre antes de que el kernel reenlace la petición y le instale el user
    // resolver, así que `$request->user()` es null aunque el guard de Sanctum
    // ya haya resuelto el token. El limitador debe mirar el guard, no la
    // petición; si no, el tráfico autenticado cae en el cupo anónimo.
    actingAsRole(RoleEnum::ADMIN);

    $request = HttpRequest::create('/api/cars', 'GET');

    expect(RateLimiter::limiter('api-anon')($request))->toBeInstanceOf(Unlimited::class);
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

it('aplica un cupo propio a la generación de PDF', function () {
    config([
        'rate_limits.api' => 10,
        'rate_limits.reports' => 10,
        'rate_limits.reports_pdf' => 1,
    ]);

    actingAsRole(RoleEnum::ADMIN);

    $this->get('/api/reports/cars/pdf')->assertOk();

    $this->get('/api/reports/cars/pdf')
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    // El JSON de reportes usa otro cupo y sigue disponible.
    $this->getJson('/api/reports/cars')->assertOk();
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
        ->and(config('rate_limits.login_ip'))->toBe(20)
        ->and(config('rate_limits.api_anon'))->toBe(30)
        ->and(config('rate_limits.api'))->toBe(120)
        ->and(config('rate_limits.reports'))->toBe(30)
        ->and(config('rate_limits.reports_pdf'))->toBe(5);
});
