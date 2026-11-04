<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Enums\Users\RoleEnum;
use App\Models\Audit\AuditLog;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);
});

it('audita la creación de usuarios con roles y sucursales, sin guardar la clave', function () {
    $roleId = (int) Role::where('name', RoleEnum::SELLER->value)->value('id');
    $branchId = Branch::firstOrFail()->id;

    $created = $this->postJson('/api/users', [
        'name' => 'Auditado',
        'last_name' => 'Prueba',
        'document_number' => 'AUD10001',
        'code' => 'USR-AUD1',
        'email' => 'audit-created@example.test',
        'password' => 'temporal-123',
        'password_confirmation' => 'temporal-123',
        'roles' => [$roleId],
        'branches' => [$branchId],
    ])->assertCreated()->json('data');

    $log = AuditLog::query()->where('action', AuditAction::USER_CREATED->value)->firstOrFail();

    expect($log->auditable_type)->toBe('user')
        ->and($log->auditable_id)->toBe($created['id'])
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->new_values['code'])->toBe('USR-AUD1')
        ->and($log->new_values['roles'])->toBe([$roleId])
        ->and($log->new_values['branches'])->toBe([$branchId])
        ->and($log->new_values)->not->toHaveKey('password')
        ->and($log->old_values)->toBe([]);
});

it('audita cambios, activación, desactivación, roles y contraseña del usuario', function () {
    $target = User::factory()->create();
    $managerRoleId = (int) Role::where('name', RoleEnum::MANAGER->value)->value('id');

    $this->patchJson("/api/users/{$target->id}", ['name' => 'Nombre Nuevo'])->assertOk();
    $this->patchJson("/api/users/{$target->id}", ['roles' => [$managerRoleId]])->assertOk();
    $this->patchJson("/api/users/{$target->id}", ['is_active' => false])->assertOk();
    $this->patchJson("/api/users/{$target->id}", [
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ])->assertOk();
    $this->patchJson("/api/users/{$target->id}", ['is_active' => true])->assertOk();

    $logs = AuditLog::query()
        ->where('auditable_type', 'user')
        ->where('auditable_id', $target->id)
        ->orderBy('id')
        ->get();

    $actions = $logs->map(fn (AuditLog $log): string => $log->action->value)->all();

    expect($actions)->toBe([
        AuditAction::USER_UPDATED->value,
        AuditAction::USER_ROLES_CHANGED->value,
        AuditAction::USER_UPDATED->value,
        AuditAction::USER_DEACTIVATED->value,
        AuditAction::USER_PASSWORD_CHANGED->value,
        AuditAction::USER_UPDATED->value,
        AuditAction::USER_ACTIVATED->value,
    ]);

    $updated = $logs->firstWhere('action', AuditAction::USER_UPDATED);
    $rolesChanged = $logs->firstWhere('action', AuditAction::USER_ROLES_CHANGED);
    $passwordChanged = $logs->firstWhere('action', AuditAction::USER_PASSWORD_CHANGED);

    expect($updated?->old_values)->toHaveKey('name')
        ->and($rolesChanged?->old_values['roles'])->toBe([])
        ->and($rolesChanged?->new_values['roles'])->toBe([$managerRoleId])
        ->and($passwordChanged?->old_values)->not->toHaveKey('password')
        ->and($passwordChanged?->new_values)->not->toHaveKey('password');
});

it('audita el borrado y el reset administrativo de contraseña', function () {
    $target = User::factory()->create();

    $this->postJson("/api/users/{$target->id}/reset-password", [
        'password' => 'temporal-456',
        'password_confirmation' => 'temporal-456',
    ])->assertOk();

    $this->deleteJson("/api/users/{$target->id}")->assertNoContent();

    $reset = AuditLog::query()
        ->where('action', AuditAction::USER_PASSWORD_RESET->value)
        ->where('auditable_id', $target->id)
        ->firstOrFail();

    $deleted = AuditLog::query()
        ->where('action', AuditAction::USER_DELETED->value)
        ->where('auditable_id', $target->id)
        ->firstOrFail();

    expect($reset->new_values['must_change_password'])->toBeTrue()
        ->and($reset->new_values)->not->toHaveKey('password')
        ->and($deleted->old_values['email'])->toBe($target->email)
        ->and($deleted->old_values)->not->toHaveKey('password');
});

it('audita el cambio de contraseña de autoservicio', function () {
    $user = User::factory()->create(['password' => 'password']);
    $token = $user->createToken('self-password')->plainTextToken;

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])
        ->assertOk();

    $log = AuditLog::query()
        ->where('action', AuditAction::USER_PASSWORD_CHANGED->value)
        ->where('auditable_id', $user->id)
        ->firstOrFail();

    expect($log->user_id)->toBe($user->id)
        ->and($log->old_values)->not->toHaveKey('password')
        ->and($log->new_values)->toMatchArray(['must_change_password' => false]);
});
