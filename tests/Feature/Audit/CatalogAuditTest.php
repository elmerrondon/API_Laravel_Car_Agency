<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Locations\Country;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);
});

it('audita el ciclo de vida de una marca con actor, IP y alias', function () {
    $country = Country::firstOrFail();

    $created = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->withHeader('User-Agent', 'CatalogAudit/1.0')
        ->postJson('/api/brands', [
            'name' => 'Marca Auditada',
            'description' => 'Marca para probar la auditoría',
            'country_id' => $country->id,
        ])->assertCreated()->json('data');

    $this->patchJson("/api/brands/{$created['id']}", [
        'name' => 'Marca Renombrada',
    ])->assertOk();

    $this->deleteJson("/api/brands/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::CATALOG_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::CATALOG_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::CATALOG_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->user_id)->toBe($this->admin->id)
        ->and($createdLog->ip_address)->toBe('203.0.113.20')
        ->and($createdLog->user_agent)->toBe('CatalogAudit/1.0')
        ->and($createdLog->action)->toBe(AuditAction::CATALOG_CREATED)
        ->and($createdLog->auditable_type)->toBe('brand')
        ->and($createdLog->new_values)->toMatchArray([
            'name' => 'Marca Auditada',
            'description' => 'Marca para probar la auditoría',
            'country_id' => $country->id,
            'is_active' => true,
        ])
        ->and($updatedLog->auditable_type)->toBe('brand')
        ->and($updatedLog->old_values['name'])->toBe('Marca Auditada')
        ->and($updatedLog->new_values['name'])->toBe('Marca Renombrada')
        ->and($deletedLog->auditable_type)->toBe('brand')
        ->and($deletedLog->old_values['name'])->toBe('Marca Renombrada');
});

it('audita el ciclo de vida de un impuesto con valores antiguos y nuevos', function () {
    $created = $this->postJson('/api/taxes', [
        'name' => 'Impuesto Auditado',
        'code' => 'TAX0001',
        'percentage' => 7.5,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/taxes/{$created['id']}", [
        'name' => 'Impuesto Actualizado',
        'percentage' => 8.25,
    ])->assertOk();

    $this->deleteJson("/api/taxes/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::CATALOG_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::CATALOG_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::CATALOG_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->auditable_type)->toBe('tax')
        ->and($createdLog->new_values['code'])->toBe('TAX0001')
        ->and($createdLog->new_values['percentage'])->toEqual(7.5)
        ->and($updatedLog->old_values['name'])->toBe('Impuesto Auditado')
        ->and($updatedLog->old_values['percentage'])->toEqual(7.5)
        ->and($updatedLog->new_values['name'])->toBe('Impuesto Actualizado')
        ->and($updatedLog->new_values['percentage'])->toEqual(8.25)
        ->and($deletedLog->old_values['name'])->toBe('Impuesto Actualizado');
});

it('no audita una actualización de catálogo que no cambia ningún valor', function () {
    $country = Country::firstOrFail();

    $created = $this->postJson('/api/brands', [
        'name' => 'Marca Sin Cambios',
        'description' => 'Misma descripción',
        'country_id' => $country->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/brands/{$created['id']}", [
        'name' => 'Marca Sin Cambios',
        'description' => 'Misma descripción',
        'country_id' => $country->id,
    ])->assertOk();

    expect(AuditLog::query()
        ->where('action', AuditAction::CATALOG_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->count())->toBe(0);
});
