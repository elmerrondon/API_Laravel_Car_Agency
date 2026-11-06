<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Services\Audit\AuditService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-06 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Crea un registro de auditoría y lo envejece los días indicados.
 */
function auditLogDaysAgo(int $daysAgo): AuditLog
{
    $log = app(AuditService::class)->record(AuditAction::AUTH_LOGIN);

    expect($log)->not->toBeNull();

    AuditLog::query()
        ->whereKey($log->id)
        ->update(['created_at' => now()->subDays($daysAgo)]);

    return $log;
}

it('elimina los registros más antiguos que la retención y conserva los recientes', function () {
    config(['audit.retention_days' => 90]);

    $old = auditLogDaysAgo(91);
    $recent = auditLogDaysAgo(89);

    $this->artisan('audit:prune')
        ->expectsOutputToContain('1 registros')
        ->assertSuccessful();

    expect(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('respeta el valor de --days por encima de la configuración', function () {
    config(['audit.retention_days' => 90]);

    $old = auditLogDaysAgo(11);
    $recent = auditLogDaysAgo(9);

    $this->artisan('audit:prune', ['--days' => 10])->assertSuccessful();

    expect(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('rechaza una retención no positiva', function () {
    $log = auditLogDaysAgo(1);

    $this->artisan('audit:prune', ['--days' => 0])
        ->expectsOutputToContain('entero positivo')
        ->assertExitCode(1);

    expect(AuditLog::query()->whereKey($log->id)->exists())->toBeTrue();
});
