<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Users\User;
use App\Services\Audit\AuditService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('protege la consulta de auditoría con autenticación y permiso', function () {
    $this->getJson('/api/audit-logs')->assertUnauthorized();

    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());
    $this->getJson('/api/audit-logs')->assertForbidden();

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());

    $this->getJson('/api/audit-logs')->assertOk();
});

it('lista y muestra la auditoría con paginación estable', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $target = User::where('email', 'vendedor@caragency.test')->firstOrFail();

    Sanctum::actingAs($admin);

    $service = app(AuditService::class);

    $service->record(AuditAction::AUTH_LOGIN, $admin, [], ['email' => $admin->email]);
    $service->record(AuditAction::USER_ROLES_CHANGED, $target, ['roles' => []], ['roles' => [1]]);
    $service->record(AuditAction::USER_UPDATED, $target, ['name' => 'Viejo'], ['name' => 'Nuevo']);

    $response = $this->getJson('/api/audit-logs?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.action', AuditAction::USER_UPDATED->value);

    expect($response->json('links.next'))->toContain('per_page=2');

    $id = $response->json('data.0.id');

    $this->getJson("/api/audit-logs/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.action', AuditAction::USER_UPDATED->value)
        ->assertJsonPath('data.user.id', $admin->id);
});

it('filtra por usuario, acción, recurso y rango de fechas inclusivo', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $target = User::where('email', 'vendedor@caragency.test')->firstOrFail();

    Sanctum::actingAs($admin);

    $service = app(AuditService::class);

    Carbon::setTestNow('2026-10-05 09:00:00');
    $service->record(AuditAction::AUTH_LOGIN, $admin, [], ['email' => $admin->email]);

    Carbon::setTestNow('2026-10-10 18:30:00');
    $service->record(AuditAction::USER_UPDATED, $target, ['name' => 'A'], ['name' => 'B']);

    Carbon::setTestNow();

    $this->getJson('/api/audit-logs?from=2026-10-10&to=2026-10-10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', AuditAction::USER_UPDATED->value);

    $this->getJson('/api/audit-logs?from=2026-10-01&to=2026-10-05')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', AuditAction::AUTH_LOGIN->value);

    $this->getJson('/api/audit-logs?action=auth.login')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/audit-logs?user_id='.$admin->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/audit-logs?auditable_type=user&auditable_id={$target->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.auditable_id', $target->id);
});

it('valida los filtros del listado de auditoría', function () {
    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());

    foreach ([
        'action=nope',
        'auditable_type=nope',
        'from=2026-10-01',
        'to=2026-10-01',
        'from=2026-10-10&to=2026-10-01',
        'per_page=101',
    ] as $query) {
        $this->getJson("/api/audit-logs?{$query}")->assertUnprocessable();
    }

    $this->getJson('/api/audit-logs?user_id=999999')->assertUnprocessable();
});

it('no permite escribir auditoría desde la API', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $log = app(AuditService::class)->record(AuditAction::AUTH_LOGIN, $admin, [], ['email' => $admin->email]);

    expect($log)->not->toBeNull();

    $this->postJson('/api/audit-logs', [])->assertStatus(405);
    $this->putJson("/api/audit-logs/{$log->id}", [])->assertStatus(405);
    $this->deleteJson("/api/audit-logs/{$log->id}")->assertStatus(405);

    expect(AuditLog::count())->toBe(1);
});

it('redacta secretos en los valores guardados y expuestos', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $log = app(AuditService::class)->record(
        AuditAction::USER_PASSWORD_CHANGED,
        $admin,
        ['password' => 'vieja-clave', 'nested' => ['access_token' => 'abc', 'safe' => 'visible']],
        ['password' => 'nueva-clave', 'remember_token' => 'recuerdo'],
    );

    expect($log)->not->toBeNull();
    expect($log->old_values['password'])->toBe('[REDACTED]')
        ->and($log->old_values['nested']['access_token'])->toBe('[REDACTED]')
        ->and($log->old_values['nested']['safe'])->toBe('visible')
        ->and($log->new_values['password'])->toBe('[REDACTED]')
        ->and($log->new_values['remember_token'])->toBe('[REDACTED]');

    $this->getJson("/api/audit-logs/{$log->id}")
        ->assertOk()
        ->assertJsonPath('data.old_values.password', '[REDACTED]')
        ->assertJsonPath('data.new_values.password', '[REDACTED]')
        ->assertJsonPath('data.old_values.nested.access_token', '[REDACTED]');
});
