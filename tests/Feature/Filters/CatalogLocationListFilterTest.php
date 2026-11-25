<?php

declare(strict_types=1);

use App\Models\Cars\Brand;
use App\Models\Cars\CarModel;
use App\Models\Locations\Branch;
use App\Models\Locations\City;
use App\Models\Locations\Country;
use App\Models\Locations\State;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
});

it('filtra marcas por país, estado y prefijo sin distinguir mayúsculas', function () {
    $japan = Country::where('name', 'Japon')->firstOrFail();

    $byCountry = $this->getJson('/api/brands?country_id='.$japan->id.'&per_page=100')->assertOk();
    expect($byCountry->json('data'))->not->toBeEmpty();

    foreach ($byCountry->json('data') as $brand) {
        expect($brand['country_id'])->toBe($japan->id);
    }

    $bySearch = $this->getJson('/api/brands?search=toy')->assertOk();
    expect(array_column($bySearch->json('data'), 'name'))->toBe(['Toyota']);

    $brand = Brand::firstOrFail();
    $brand->update(['is_active' => false]);

    $byActive = $this->getJson('/api/brands?is_active=false')->assertOk();
    expect(array_column($byActive->json('data'), 'id'))->toContain($brand->id);

    foreach ($byActive->json('data') as $item) {
        expect($item['is_active'])->toBeFalse();
    }
});

it('filtra modelos por marca y catálogos por prefijo', function () {
    $corolla = CarModel::where('name', 'Corolla')->firstOrFail();

    $byBrand = $this->getJson('/api/car-models?brand_id='.$corolla->brand_id.'&per_page=100')->assertOk();
    expect($byBrand->json('data'))->not->toBeEmpty();

    foreach ($byBrand->json('data') as $model) {
        expect($model['brand_id'])->toBe($corolla->brand_id);
    }

    $types = $this->getJson('/api/car-types?search=sed')->assertOk();
    expect(array_column($types->json('data'), 'name'))->toBe(['Sedán']);

    $currencies = $this->getJson('/api/currencies?is_base=true')->assertOk();
    expect($currencies->json('data'))->toHaveCount(1)
        ->and($currencies->json('data.0.is_base'))->toBeTrue();
});

it('filtra estados, ciudades y sucursales por jerarquía', function () {
    $state = State::query()->orderBy('id')->firstOrFail();
    $city = City::where('state_id', $state->id)->firstOrFail();
    $branch = Branch::where('city_id', $city->id)->firstOrFail();

    $states = $this->getJson('/api/states?country_id='.$state->country_id.'&per_page=100')->assertOk();
    foreach ($states->json('data') as $item) {
        expect($item['country_id'])->toBe($state->country_id);
    }

    $cities = $this->getJson('/api/cities?state_id='.$city->state_id.'&per_page=100')->assertOk();
    foreach ($cities->json('data') as $item) {
        expect($item['state_id'])->toBe($city->state_id);
    }

    $branches = $this->getJson('/api/branches?city_id='.$branch->city_id.'&per_page=100')->assertOk();
    expect(array_column($branches->json('data'), 'id'))->toContain($branch->id);

    foreach ($branches->json('data') as $item) {
        expect($item['city_id'])->toBe($branch->city_id);
    }

    $bySearch = $this->getJson('/api/branches?search=agencia')->assertOk();
    expect(array_column($bySearch->json('data'), 'id'))->toBe(
        Branch::query()
            ->whereIn('name', ['Agencia Pueblo Nuevo', 'Agencia 5 de Julio'])
            ->orderBy('id')
            ->pluck('id')
            ->all(),
    );
});

it('rechaza filtros de catálogo y ubicación no válidos', function (string $url) {
    $this->getJson($url)->assertUnprocessable();
})->with([
    '/api/brands?country_id=999999',
    '/api/car-models?brand_id=no-es-enter',
    '/api/branches?is_active=quizas',
    '/api/currencies?is_base=no',
]);
