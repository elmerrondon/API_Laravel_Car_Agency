<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Users\User;

/*
|--------------------------------------------------------------------------
| Proxies de confianza
|--------------------------------------------------------------------------
|
| Solo los proxies configurados en TRUSTED_PROXIES pueden fijar la IP real
| mediante X-Forwarded-For. La auditoría es el consumidor más sensible: si la
| cabecera se aceptara de cualquiera, un atacante podría falsear su origen.
|
*/

it('usa la IP reenviada cuando la conexión viene de un proxy de confianza', function () {
    config(['trustedproxy.proxies' => '203.0.113.10']);

    $user = User::factory()->create([
        'email' => 'trusted-proxy@example.test',
        'password' => 'password',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '198.51.100.7')
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertOk();

    $log = AuditLog::query()->where('action', AuditAction::AUTH_LOGIN->value)->firstOrFail();

    expect($log->ip_address)->toBe('198.51.100.7');
});

it('ignora la cabecera reenviada cuando la conexión no es de confianza', function () {
    config(['trustedproxy.proxies' => '203.0.113.10']);

    $user = User::factory()->create([
        'email' => 'untrusted-proxy@example.test',
        'password' => 'password',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])
        ->withHeader('X-Forwarded-For', '198.51.100.7')
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertOk();

    $log = AuditLog::query()->where('action', AuditAction::AUTH_LOGIN->value)->firstOrFail();

    expect($log->ip_address)->toBe('192.0.2.50');
});

it('descarta una cadena reenviada de varios saltos aunque venga de un proxy de confianza', function () {
    config(['trustedproxy.proxies' => '203.0.113.10']);

    $user = User::factory()->create([
        'email' => 'multi-hop@example.test',
        'password' => 'password',
    ]);

    // El cliente intenta colar su IP delante de la que añade el proxy. Sin
    // conocer al proveedor no se puede saber cuál es la real: se descarta la
    // cabecera y queda la IP del par inmediato.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '198.51.100.7, 10.0.0.9')
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertOk();

    $log = AuditLog::query()->where('action', AuditAction::AUTH_LOGIN->value)->firstOrFail();

    expect($log->ip_address)->toBe('203.0.113.10');
});

it('cuenta login_ip por la IP de la conexión aunque el cliente falsifique la cadena', function () {
    config([
        'trustedproxy.proxies' => '203.0.113.10',
        'rate_limits.login_ip' => 2,
    ]);

    foreach ([1, 2] as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->withHeader('X-Forwarded-For', '198.51.100.'.$i.', 10.0.0.'.$i)
            ->postJson('/api/auth/login', [
                'email' => 'spray'.$i.'@example.test',
                'password' => 'wrong',
            ])
            ->assertUnauthorized();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '198.51.100.99, 10.0.0.99')
        ->postJson('/api/auth/login', [
            'email' => 'spray3@example.test',
            'password' => 'wrong',
        ])
        ->assertStatus(429);
});

it('cuenta el cupo anónimo por la IP real aunque llegue X-Forwarded-For de un origen no confiable', function () {
    config([
        'trustedproxy.proxies' => '203.0.113.10',
        'rate_limits.api_anon' => 1,
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])
        ->withHeader('X-Forwarded-For', '198.51.100.7')
        ->getJson('/api/cars')
        ->assertUnauthorized();

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])
        ->withHeader('X-Forwarded-For', '198.51.100.8')
        ->getJson('/api/cars')
        ->assertStatus(429);
});

it('respeta la IP reenviada por un proxy de confianza en el cupo anónimo', function () {
    config([
        'trustedproxy.proxies' => '203.0.113.10',
        'rate_limits.api_anon' => 1,
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '198.51.100.7')
        ->getJson('/api/cars')
        ->assertUnauthorized();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-For', '198.51.100.8')
        ->getJson('/api/cars')
        ->assertUnauthorized();
});
