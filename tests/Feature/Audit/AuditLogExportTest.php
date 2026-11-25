<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Cars\Car;
use App\Models\Users\User;
use App\Services\Audit\AuditService;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Devuelve las filas del CSV (sin la cabecera) como arrays de celdas.
 *
 * @return list<list<string>>
 */
function csvRows(string $csv): array
{
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);
    array_shift($rows);

    return $rows;
}

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

it('aplica al CSV los mismos filtros que al listado', function () {
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $manager = User::where('email', 'manager@caragency.test')->firstOrFail();
    $car = Car::firstOrFail();

    Sanctum::actingAs($admin);

    $service = app(AuditService::class);

    $adminLogin = $service->record(AuditAction::AUTH_LOGIN, $admin, actor: $admin);
    $managerUpdate = $service->record(AuditAction::USER_UPDATED, $manager, actor: $manager);
    $adminCarOld = $service->record(AuditAction::CAR_CREATED, $car, actor: $admin);
    $managerCarNew = $service->record(AuditAction::CAR_CREATED, $car, actor: $manager);

    AuditLog::query()->whereKey($adminCarOld->id)->update(['created_at' => '2026-01-15 10:00:00']);
    AuditLog::query()->whereKey($managerCarNew->id)->update(['created_at' => '2026-03-15 10:00:00']);

    $ids = fn (string $query): array => array_map(
        'intval',
        array_column(csvRows($this->get('/api/audit-logs/export'.$query)->assertOk()->streamedContent()), 0),
    );

    expect($ids(''))->toBe([
        $managerCarNew->id,
        $adminCarOld->id,
        $managerUpdate->id,
        $adminLogin->id,
    ])
        ->and($ids('?user_id='.$admin->id))->toBe([$adminCarOld->id, $adminLogin->id])
        ->and($ids('?auditable_type=car'))->toBe([$managerCarNew->id, $adminCarOld->id])
        ->and($ids('?from=2026-01-01&to=2026-01-31'))->toBe([$adminCarOld->id])
        ->and($ids('?'.http_build_query([
            'user_id' => $admin->id,
            'auditable_type' => 'car',
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ])))->toBe([$adminCarOld->id]);
});
