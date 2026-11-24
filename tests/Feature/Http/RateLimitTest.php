<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

/**
 * Devuelve los límites que el limitador registrado aplicaría a una petición.
 *
 * @return list<Limit|Unlimited>
 */
function limitsFor(string $name): array
{
    $limiter = RateLimiter::limiter($name);
    $limits = $limiter(HttpRequest::create('/api/cars', 'GET'));

    return is_array($limits) ? $limits : [$limits];
}

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

it('aplica el cupo general al token de una cuenta desactivada', function () {
    // `active` aborta con 401, pero ya después de `throttle:api`: el token de
    // una cuenta inactiva no puede esquivar el cupo por usuario.
    config(['rate_limits.api' => 2]);

    Sanctum::actingAs(User::factory()->create(['is_active' => false]));

    $this->getJson('/api/cars')->assertUnauthorized();
    $this->getJson('/api/cars')->assertUnauthorized();

    $this->getJson('/api/cars')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('aplica el cupo general a una cuenta obligada a cambiar la clave', function () {
    // Mismo caso con `password.changed`: responde 403, pero el intento ya
    // consumió el cupo.
    config([
        'rate_limits.api' => 2,
        'security.enforce_password_change' => true,
    ]);

    Sanctum::actingAs(User::factory()->create(['must_change_password' => true]));

    $this->getJson('/api/cars')->assertForbidden();
    $this->getJson('/api/cars')->assertForbidden();

    $this->getJson('/api/cars')
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

it('limita por IP el healthcheck público', function () {
    // `/up` está fuera del grupo `api`: sin su limitador propio, un anónimo
    // podría usarlo para sondear la base y la caché sin límite.
    config(['rate_limits.health' => 1]);

    $this->get('/up')->assertOk();

    $this->get('/up')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('aplica un cupo propio a la exportación de auditoría', function () {
    config([
        'rate_limits.api' => 10,
        'rate_limits.audit_export' => 1,
    ]);

    actingAsRole(RoleEnum::ADMIN);

    $this->get('/api/audit-logs/export')->assertOk();

    $this->get('/api/audit-logs/export')
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    // El listado sigue usando el cupo general y no se ve afectado.
    $this->getJson('/api/audit-logs')->assertOk();
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
        ->and(config('rate_limits.reports_pdf'))->toBe(5)
        ->and(config('rate_limits.health'))->toBe(30)
        ->and(config('rate_limits.audit_export'))->toBe(10);
});

it('conecta cada RATE_LIMIT_* con su limitador', function () {
    config([
        'rate_limits.login' => 11,
        'rate_limits.login_ip' => 12,
        'rate_limits.api_anon' => 13,
        'rate_limits.api' => 14,
        'rate_limits.reports' => 15,
        'rate_limits.reports_pdf' => 16,
        'rate_limits.health' => 17,
        'rate_limits.audit_export' => 18,
    ]);

    $attempts = fn (string $name): array => array_map(
        fn (object $limit): int => $limit instanceof Limit ? $limit->maxAttempts : -1,
        limitsFor($name),
    );

    expect($attempts('login'))->toBe([11, 12])
        ->and($attempts('api-anon'))->toBe([13])
        ->and($attempts('api'))->toBe([14])
        ->and($attempts('reports'))->toBe([15])
        ->and($attempts('reports-pdf'))->toBe([16])
        ->and($attempts('health'))->toBe([17])
        ->and($attempts('audit-export'))->toBe([18]);
});

it('no cuenta el preflight de CORS como tráfico anónimo', function () {
    config(['rate_limits.api_anon' => 1]);

    // El middleware global procesa el OPTIONS antes de despachar la ruta, con
    // o sin cabeceras de preflight; solo importa que no consuma el cupo.
    $this->call('OPTIONS', '/api/cars');
    $this->call('OPTIONS', '/api/cars');

    $this->getJson('/api/cars')->assertUnauthorized();
    $this->getJson('/api/cars')->assertStatus(429);
});

it('no cuenta el login como tráfico anónimo', function () {
    config([
        'rate_limits.api_anon' => 1,
        'rate_limits.login' => 100,
        'rate_limits.login_ip' => 100,
    ]);

    $payload = ['email' => 'anonimo@example.test', 'password' => 'clave-erronea'];

    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();

    // El cupo anónimo sigue intacto: la primera petición al API es la que lo
    // agota, no los intentos de login (que tienen sus propios limitadores).
    $this->getJson('/api/cars')->assertUnauthorized();
    $this->getJson('/api/cars')->assertStatus(429);
});
