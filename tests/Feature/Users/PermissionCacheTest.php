<?php

declare(strict_types=1);

use App\DTOs\Users\RoleData;
use App\Enums\Users\PermissionEnum;
use App\Enums\Users\RoleEnum;
use App\Models\Locations\Country;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\Services\Users\RoleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Cuenta las consultas que dispara el callback.
 */
function permissionQueryCount(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();

        return count(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
    }
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $this->seller = User::where('email', 'vendedor@caragency.test')->firstOrFail();
});

it('resuelve los permisos una sola vez y luego los sirve desde caché', function () {
    $first = permissionQueryCount(fn () => $this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS));
    $second = permissionQueryCount(fn () => $this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS));

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0);
});

it('devuelve los permisos efectivos de todos los roles, sin repetir', function () {
    $names = $this->admin->permissionNames();

    expect($names)->toBe(array_values(array_unique($names)))
        ->and($names)->toHaveCount(count(PermissionEnum::cases()))
        ->and($names)->toContain(PermissionEnum::VIEW_CARS->value);
});

it('invalida la caché al cambiar los roles del usuario', function () {
    Sanctum::actingAs($this->seller);

    $payload = [
        'name' => 'Marca desde la caché',
        'description' => 'Debe crearse tras el cambio de rol',
        'country_id' => Country::firstOrFail()->id,
    ];

    // El vendedor tiene view_catalogs pero no manage_catalogs: queda cacheado.
    $this->postJson('/api/brands', $payload)->assertForbidden();

    Sanctum::actingAs($this->admin);

    $managerRoleId = Role::where('name', RoleEnum::MANAGER->value)->value('id');

    $this->patchJson("/api/users/{$this->seller->id}", ['roles' => [$managerRoleId]])->assertOk();

    Sanctum::actingAs($this->seller);

    $this->postJson('/api/brands', $payload)->assertCreated();
});

it('invalida la caché de los usuarios del rol al cambiar sus permisos', function () {
    $role = Role::create(['name' => 'Catálogos', 'description' => 'Rol de prueba']);
    $role->permissions()->sync([Permission::where('name', PermissionEnum::VIEW_CATALOGS->value)->value('id')]);

    $user = User::factory()->create(['email' => 'cache@example.test']);
    $user->roles()->sync([$role->id]);

    expect($user->hasPermissionTo(PermissionEnum::VIEW_CATALOGS))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::MANAGE_CATALOGS))->toBeFalse();

    app(RoleService::class)->updateRole($role, new RoleData(
        permissions: [Permission::where('name', PermissionEnum::MANAGE_CATALOGS->value)->value('id')],
    ));

    expect($user->hasPermissionTo(PermissionEnum::MANAGE_CATALOGS))->toBeTrue()
        ->and($user->hasPermissionTo(PermissionEnum::VIEW_CATALOGS))->toBeFalse();
});

it('recalcula los permisos cuando caduca el TTL', function () {
    expect($this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeTrue();

    $this->seller->roles()->detach();

    // La caché sigue sirviendo el valor anterior.
    expect($this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeTrue();

    $this->travel((int) config('permissions.cache_ttl') + 1)->seconds();

    expect($this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeFalse();
});

it('permite desactivar la caché con un TTL de cero', function () {
    config(['permissions.cache_ttl' => 0]);

    expect($this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeTrue();

    $this->seller->roles()->detach();

    expect($this->seller->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeFalse();
});

it('descarta la caché del usuario al borrarlo', function () {
    Sanctum::actingAs($this->admin);

    $user = User::factory()->create(['email' => 'borrar@example.test']);
    $user->roles()->sync([Role::where('name', RoleEnum::SELLER->value)->value('id')]);

    expect($user->hasPermissionTo(PermissionEnum::VIEW_CARS))->toBeTrue();

    $this->deleteJson("/api/users/{$user->id}")->assertNoContent();

    // La clave se borra para no dejar datos de un usuario eliminado.
    expect(Cache::has("permissions:user:{$user->id}"))->toBeFalse();
});
