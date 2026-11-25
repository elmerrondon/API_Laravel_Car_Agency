<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use Illuminate\Support\Facades\Cache;

/*
|--------------------------------------------------------------------------
| Password spraying y amplificación de auditoría
|--------------------------------------------------------------------------
|
| La ventana email+IP frena la fuerza bruta contra una cuenta y la ventana por
| IP corta la rotación de emails. Los fallos se agrupan por minuto para que un
| ataque no inserte una fila de auditoría por intento ni por cada 429.
|
*/

it('limita la rotación de emails desde una misma IP', function () {
    Cache::flush();

    config([
        'rate_limits.login' => 5,
        'rate_limits.login_ip' => 3,
    ]);

    foreach (range(1, 3) as $index) {
        $this->postJson('/api/auth/login', [
            'email' => "spray-{$index}@example.test",
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/auth/login', [
        'email' => 'spray-4@example.test',
        'password' => 'wrong-password',
    ])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('acota las filas de auditoría de un ataque con rotación de emails', function () {
    Cache::flush();

    config([
        'rate_limits.login' => 5,
        'rate_limits.login_ip' => 3,
    ]);

    foreach (range(1, 6) as $index) {
        $this->postJson('/api/auth/login', [
            'email' => "audit-spray-{$index}@example.test",
            'password' => 'wrong-password',
        ])->assertStatus($index <= 3 ? 401 : 429);
    }

    $logs = AuditLog::query()
        ->where('action', AuditAction::AUTH_LOGIN_FAILED->value)
        ->orderBy('id')
        ->get();

    $reasons = $logs->map(fn (AuditLog $log): string => (string) $log->new_values['reason'])->all();

    expect($logs)->toHaveCount(2)
        ->and($reasons)->toBe(['invalid_credentials', 'throttled']);
});
