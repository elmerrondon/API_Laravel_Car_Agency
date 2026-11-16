<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Models\Audit\AuditLog;
use App\Models\Locations\Country;
use App\Models\Locations\State;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);
});

it('audita el ciclo de vida de un país con actor, IP y alias', function () {
    $created = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30'])
        ->withHeader('User-Agent', 'LocationAudit/1.0')
        ->postJson('/api/countries', [
            'name' => 'Pais Auditado',
        ])->assertCreated()->json('data');

    $this->patchJson("/api/countries/{$created['id']}", [
        'name' => 'Pais Renombrado',
    ])->assertOk();

    $this->deleteJson("/api/countries/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->user_id)->toBe($this->admin->id)
        ->and($createdLog->ip_address)->toBe('203.0.113.30')
        ->and($createdLog->user_agent)->toBe('LocationAudit/1.0')
        ->and($createdLog->auditable_type)->toBe('country')
        ->and($createdLog->new_values['name'])->toBe('Pais Auditado')
        ->and($updatedLog->auditable_type)->toBe('country')
        ->and($updatedLog->old_values['name'])->toBe('Pais Auditado')
        ->and($updatedLog->new_values['name'])->toBe('Pais Renombrado')
        ->and($deletedLog->auditable_type)->toBe('country')
        ->and($deletedLog->old_values['name'])->toBe('Pais Renombrado');
});

it('audita el ciclo de vida de un estado', function () {
    $country = Country::firstOrFail();

    $created = $this->postJson('/api/states', [
        'name' => 'Estado Auditado',
        'country_id' => $country->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/states/{$created['id']}", [
        'name' => 'Estado Renombrado',
    ])->assertOk();

    $this->deleteJson("/api/states/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->auditable_type)->toBe('state')
        ->and($createdLog->new_values['name'])->toBe('Estado Auditado')
        ->and($createdLog->new_values['country_id'])->toBe($country->id)
        ->and($updatedLog->old_values['name'])->toBe('Estado Auditado')
        ->and($updatedLog->new_values['name'])->toBe('Estado Renombrado')
        ->and($deletedLog->old_values['name'])->toBe('Estado Renombrado');
});

it('audita el ciclo de vida de una ciudad', function () {
    $state = State::firstOrFail();

    $created = $this->postJson('/api/cities', [
        'name' => 'Ciudad Auditada',
        'state_id' => $state->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/cities/{$created['id']}", [
        'name' => 'Ciudad Renombrada',
    ])->assertOk();

    $this->deleteJson("/api/cities/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::LOCATION_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->auditable_type)->toBe('city')
        ->and($createdLog->new_values['name'])->toBe('Ciudad Auditada')
        ->and($createdLog->new_values['state_id'])->toBe($state->id)
        ->and($updatedLog->old_values['name'])->toBe('Ciudad Auditada')
        ->and($updatedLog->new_values['name'])->toBe('Ciudad Renombrada')
        ->and($deletedLog->old_values['name'])->toBe('Ciudad Renombrada');
});
