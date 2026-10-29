<?php

declare(strict_types=1);

use App\Enums\Users\PermissionEnum;
use App\Enums\Users\RoleEnum;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());

    $this->sellerRoleId = Role::where('name', RoleEnum::SELLER->value)->value('id');
    $this->managerRoleId = Role::where('name', RoleEnum::MANAGER->value)->value('id');
    $this->branchId = Branch::firstOrFail()->id;

    $this->payload = [
        'name' => 'Carla',
        'last_name' => 'Ruiz',
        'document_number' => 'DOC30003',
        'code' => 'USR-900',
        'email' => 'carla@example.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'roles' => [$this->sellerRoleId],
        'branches' => [$this->branchId],
    ];
});

it('crea un usuario con roles y sucursales', function () {
    $created = $this->postJson('/api/users', $this->payload)
        ->assertCreated()
        ->assertJsonPath('data.email', 'carla@example.test')
        ->assertJsonPath('data.code', 'USR-900')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.roles.0.id', $this->sellerRoleId)
        ->assertJsonPath('data.branches.0.id', $this->branchId)
        ->json('data');

    $user = User::findOrFail($created['id']);

    expect($user->roles->pluck('id')->all())->toBe([$this->sellerRoleId])
        ->and($user->branches->pluck('id')->all())->toBe([$this->branchId])
        ->and(Hash::check('secret123', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('secret123')
        ->and($user->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::MANAGE_USERS))->toBeFalse();
});

it('rechaza email, documento y código duplicados', function () {
    $existing = User::where('email', 'vendedor@caragency.test')->firstOrFail();

    $this->postJson('/api/users', array_merge($this->payload, [
        'email' => $existing->email,
    ]))->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/users', array_merge($this->payload, [
        'document_number' => $existing->document_number,
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_number');

    $this->postJson('/api/users', array_merge($this->payload, [
        'code' => $existing->code,
    ]))->assertUnprocessable()->assertJsonValidationErrors('code');

    expect(User::where('email', 'carla@example.test')->exists())->toBeFalse();
});

it('actualiza nombre, email, roles y sucursales', function () {
    $created = $this->postJson('/api/users', $this->payload)->assertCreated()->json('data');

    $this->patchJson("/api/users/{$created['id']}", [
        'name' => 'Carla María',
        'email' => 'carla.maria@example.test',
        'roles' => [$this->managerRoleId],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Carla María')
        ->assertJsonPath('data.email', 'carla.maria@example.test')
        ->assertJsonPath('data.roles.0.id', $this->managerRoleId);

    $user = User::findOrFail($created['id']);

    expect($user->roles->pluck('id')->all())->toBe([$this->managerRoleId])
        ->and($user->hasPermissionTo(PermissionEnum::MANAGE_CATALOGS))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::MANAGE_USERS))->toBeFalse();
});

it('rechaza actualizar con el email de otro usuario', function () {
    $created = $this->postJson('/api/users', $this->payload)->assertCreated()->json('data');
    $other = User::where('email', 'manager@caragency.test')->firstOrFail();

    $this->patchJson("/api/users/{$created['id']}", ['email' => $other->email])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('revoca los tokens al desactivar la cuenta', function () {
    $created = $this->postJson('/api/users', $this->payload)->assertCreated()->json('data');

    $user = User::findOrFail($created['id']);
    $user->createToken('web');
    $user->createToken('mobile');

    expect($user->tokens()->count())->toBe(2);

    $this->patchJson("/api/users/{$created['id']}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->is_active)->toBeFalse();
});

it('borra lógicamente un usuario y revoca sus tokens', function () {
    $created = $this->postJson('/api/users', $this->payload)->assertCreated()->json('data');

    $user = User::findOrFail($created['id']);
    $user->createToken('web');

    $this->deleteJson("/api/users/{$created['id']}")->assertNoContent();

    expect(User::find($created['id']))->toBeNull()
        ->and(User::withTrashed()->find($created['id']))->not->toBeNull()
        ->and($user->tokens()->count())->toBe(0);

    $this->getJson("/api/users/{$created['id']}")->assertNotFound();
});

it('permite reutilizar email y documento de un usuario borrado', function () {
    $created = $this->postJson('/api/users', $this->payload)->assertCreated()->json('data');

    $this->deleteJson("/api/users/{$created['id']}")->assertNoContent();

    $this->postJson('/api/users', array_merge($this->payload, ['code' => 'USR-901']))
        ->assertCreated()
        ->assertJsonPath('data.email', 'carla@example.test');

    // El código es único a nivel global, también frente a usuarios borrados.
    $this->postJson('/api/users', array_merge($this->payload, ['code' => 'USR-900', 'email' => 'otra@example.test', 'document_number' => 'DOC30099']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('lista y muestra usuarios con sus relaciones', function () {
    $this->getJson('/api/users')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'email', 'roles', 'branches']], 'meta' => ['total']]);

    $user = User::where('email', 'vendedor@caragency.test')->firstOrFail();

    $this->getJson("/api/users/{$user->id}")
        ->assertOk()
        ->assertJsonPath('data.email', 'vendedor@caragency.test')
        ->assertJsonPath('data.roles.0.name', RoleEnum::SELLER->value)
        ->assertJsonCount(1, 'data.branches');
});
