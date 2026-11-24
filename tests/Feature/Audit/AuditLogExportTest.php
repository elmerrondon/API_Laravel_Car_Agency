<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Users\User;
use App\Services\Audit\AuditService;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('protege la exportación con autenticación y permiso', function () {
    $this->get('/api/audit-logs/export')->assertUnauthorized();

    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());
    $this->get('/api/audit-logs/export')->assertForbidden();

    $this->app['auth']->forgetGuards();
    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
    $this->get('/api/audit-logs/export')->assertOk();
});

it('exporta un CSV en streaming con los filtros del listado', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $service = app(AuditService::class);

    $service->record(AuditAction::AUTH_LOGIN, $admin, [], ['email' => $admin->email]);
    $service->record(AuditAction::USER_UPDATED, $admin, ['name' => 'Viejo'], ['name' => 'Nuevo']);

    $response = $this->get('/api/audit-logs/export?action=auth.login');

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->headers->get('content-disposition'))->toContain('attachment; filename=');

    $csv = $response->streamedContent();

    expect($csv)->toContain('id,action,auditable_type')
        ->toContain('auth.login')
        ->not->toContain('user.updated');
});

it('escribe el BOM y escapa todos los prefijos de inyección de fórmulas', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    Sanctum::actingAs($admin);

    $service = app(AuditService::class);
    $prefixes = ['=', '+', '-', '@', "\t", "\r", "\n"];

    foreach ($prefixes as $prefix) {
        $log = $service->record(AuditAction::USER_UPDATED, $admin, [], ['name' => 'Nuevo']);

        expect($log)->not->toBeNull();

        AuditLog::query()
            ->whereKey($log->id)
            ->update(['user_agent' => $prefix.'cmd|calc']);
    }

    $csv = $this->get('/api/audit-logs/export')->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBF");

    foreach ($prefixes as $prefix) {
        expect($csv)->toContain("'".$prefix.'cmd|calc');
    }
});
