<?php

declare(strict_types=1);

use App\Models\Cars\Brand;
use App\Models\Locations\Branch;
use App\Models\Locations\City;
use App\Models\Locations\Country;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
});

it('hace el CRUD completo de un catálogo (marcas)', function () {
    $country = Country::firstOrFail();

    $created = $this->postJson('/api/brands', [
        'name' => 'Mazda Test',
        'description' => 'Marca de prueba',
        'country_id' => $country->id,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Mazda Test')
        ->assertJsonPath('data.is_active', true)
        ->json('data');

    $this->getJson('/api/brands')
        ->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'per_page', 'total']]);

    $this->getJson("/api/brands/{$created['id']}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Mazda Test')
        ->assertJsonPath('data.country_id', $country->id);

    $this->patchJson("/api/brands/{$created['id']}", [
        'description' => 'Descripción actualizada',
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.description', 'Descripción actualizada')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.name', 'Mazda Test');

    $this->deleteJson("/api/brands/{$created['id']}")->assertNoContent();

    $this->getJson("/api/brands/{$created['id']}")->assertNotFound();

    expect(Brand::find($created['id']))->toBeNull();
});

it('hace el CRUD completo de una ubicación (sucursales) y genera el código', function () {
    $city = City::with('state')->firstOrFail();

    $created = $this->postJson('/api/branches', [
        'name' => 'Sucursal de Prueba',
        'address' => 'Calle Falsa 123',
        'description' => 'Sucursal temporal para tests',
        'zip_code' => 'A12345',
        'city_id' => $city->id,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sucursal de Prueba')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.city.id', $city->id)
        ->json('data');

    expect($created['code'])->toMatch('/^SUC-\d{4}$/')
        ->and($created['code'])->toBe(Branch::whereKey($created['id'])->value('code'));

    $this->getJson("/api/branches/{$created['id']}")
        ->assertOk()
        ->assertJsonPath('data.code', $created['code'])
        ->assertJsonPath('data.city.state.id', $city->state_id);

    $this->patchJson("/api/branches/{$created['id']}", [
        'description' => 'Sucursal actualizada',
        'is_active' => false,
    ])->assertOk()
        ->assertJsonPath('data.description', 'Sucursal actualizada')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.code', $created['code']);

    $this->deleteJson("/api/branches/{$created['id']}")->assertNoContent();

    expect(Branch::find($created['id']))->toBeNull();
});

it('permite al vendedor consultar catálogos y ubicaciones pero no gestionarlos', function () {
    Sanctum::actingAs(User::where('email', 'vendedor@caragency.test')->firstOrFail());

    $this->getJson('/api/brands')->assertOk();
    $this->getJson('/api/colors')->assertOk();
    $this->getJson('/api/branches')->assertOk();
    $this->getJson('/api/cities')->assertOk();

    $brand = Brand::firstOrFail();
    $country = Country::firstOrFail();
    $city = City::firstOrFail();

    $this->postJson('/api/brands', [
        'name' => 'Marca Prohibida',
        'description' => 'No debería crearse',
        'country_id' => $country->id,
    ])->assertForbidden();

    $this->patchJson("/api/brands/{$brand->id}", ['is_active' => false])->assertForbidden();
    $this->deleteJson("/api/brands/{$brand->id}")->assertForbidden();

    $this->postJson('/api/branches', [
        'name' => 'Sucursal Prohibida',
        'address' => 'Calle Falsa 123',
        'zip_code' => 'A12345',
        'city_id' => $city->id,
    ])->assertForbidden();

    expect(Brand::where('name', 'Marca Prohibida')->exists())->toBeFalse()
        ->and($brand->fresh()->is_active)->toBeTrue()
        ->and(Branch::where('name', 'Sucursal Prohibida')->exists())->toBeFalse();
});

it('permite al manager gestionar catálogos pero no ubicaciones', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());

    $country = Country::firstOrFail();

    $created = $this->postJson('/api/brands', [
        'name' => 'Marca del Manager',
        'description' => 'Creada por un manager',
        'country_id' => $country->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/brands/{$created['id']}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    // El rol manager no incluye `manage_locations` en la matriz de permisos.
    $this->patchJson('/api/branches/'.Branch::firstOrFail()->id, ['description' => 'No permitido'])
        ->assertForbidden();
});

it('valida el rango de per_page en los listados', function () {
    foreach (['0', '101', '-1', 'abc'] as $invalid) {
        $this->getJson("/api/brands?per_page={$invalid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->getJson("/api/branches?per_page={$invalid}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    $this->getJson('/api/brands?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/branches?per_page=100')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});
