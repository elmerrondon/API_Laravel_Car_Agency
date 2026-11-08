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
