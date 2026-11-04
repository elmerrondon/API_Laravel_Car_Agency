<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Users\User;
use Illuminate\Support\Facades\Cache;

it('audita un login correcto con actor, IP y User-Agent', function () {
    $user = User::factory()->create([
        'email' => 'audit-login@example.test',
        'password' => 'password',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('User-Agent', 'AuditAgent/1.0')
        ->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertOk();

    $log = AuditLog::query()->where('action', AuditAction::AUTH_LOGIN->value)->firstOrFail();

    expect($log->user_id)->toBe($user->id)
        ->and($log->ip_address)->toBe('203.0.113.10')
        ->and($log->user_agent)->toBe('AuditAgent/1.0')
        ->and($log->new_values['email'])->toBe($user->email)
        ->and($log->new_values)->not->toHaveKey('password');
});

it('audita un login fallido sin revelar si el email existe ni guardar la clave', function () {
    User::factory()->create(['email' => 'audit-failure@example.test']);

    $this->postJson('/api/auth/login', [
        'email' => 'audit-failure@example.test',
        'password' => 'clave-incorrecta',
    ])->assertUnauthorized();

    $log = AuditLog::query()->where('action', AuditAction::AUTH_LOGIN_FAILED->value)->firstOrFail();

    expect($log->user_id)->toBeNull()
        ->and($log->new_values['email'])->toBe('audit-failure@example.test')
        ->and($log->new_values['reason'])->toBe('invalid_credentials')
        ->and($log->new_values)->not->toHaveKey('password');
});

it('audita como throttled el intento bloqueado por el rate limit', function () {
    Cache::flush();
    config(['rate_limits.login' => 1]);

    $payload = [
        'email' => 'audit-throttle@example.test',
        'password' => 'clave-incorrecta',
    ];

    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    $this->postJson('/api/auth/login', $payload)->assertStatus(429);

    $logs = AuditLog::query()
        ->where('action', AuditAction::AUTH_LOGIN_FAILED->value)
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->new_values['reason'])->toBe('invalid_credentials')
        ->and($logs[1]->new_values['reason'])->toBe('throttled')
        ->and($logs[1]->new_values['email'])->toBe('audit-throttle@example.test');
});

it('audita el logout y la renovación del token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('audit-logout')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

    $token = $user->createToken('audit-refresh')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/refresh')->assertOk();

    $actions = AuditLog::query()
        ->whereIn('action', [AuditAction::AUTH_LOGOUT->value, AuditAction::AUTH_TOKEN_REFRESHED->value])
        ->orderBy('id')
        ->pluck('action')
        ->all();

    expect($actions)->toBe([
        AuditAction::AUTH_LOGOUT,
        AuditAction::AUTH_TOKEN_REFRESHED,
    ]);
});
