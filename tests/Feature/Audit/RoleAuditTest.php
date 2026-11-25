<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Enums\Users\PermissionEnum;
use App\Models\Audit\AuditLog;
use App\Models\Users\Permission;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);
});

it('audita el ciclo de vida de un rol con sus permisos', function () {
    $viewCars = (int) Permission::where('name', PermissionEnum::VIEW_CARS->value)->value('id');
    $viewSales = (int) Permission::where('name', PermissionEnum::VIEW_SALES->value)->value('id');

    $role = $this->postJson('/api/roles', [
        'name' => 'Auditor de prueba',
        'description' => 'Rol para probar la auditoría',
        'permissions' => [$viewCars],
    ])->assertCreated()->json('data');

    $this->patchJson("/api/roles/{$role['id']}", [
        'description' => 'Descripción actualizada',
        'permissions' => [$viewCars, $viewSales],
    ])->assertOk();

    $this->deleteJson("/api/roles/{$role['id']}")->assertNoContent();

    $created = AuditLog::query()
        ->where('action', AuditAction::ROLE_CREATED->value)
        ->where('auditable_id', $role['id'])
        ->firstOrFail();

    $updated = AuditLog::query()
        ->where('action', AuditAction::ROLE_UPDATED->value)
        ->where('auditable_id', $role['id'])
        ->firstOrFail();

    $deleted = AuditLog::query()
        ->where('action', AuditAction::ROLE_DELETED->value)
        ->where('auditable_id', $role['id'])
        ->firstOrFail();

    expect($created->auditable_type)->toBe('role')
        ->and($created->new_values['permissions'])->toBe([$viewCars])
        ->and($updated->old_values['description'])->toBe('Rol para probar la auditoría')
        ->and($updated->new_values['description'])->toBe('Descripción actualizada')
        ->and($updated->new_values['permissions'])->toBe([$viewCars, $viewSales])
        ->and($deleted->old_values['permissions'])->toBe([$viewCars, $viewSales]);
});
