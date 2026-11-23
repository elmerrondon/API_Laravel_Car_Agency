<?php

declare(strict_types=1);

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
use App\Models\Cars\CarModel;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
});

it('filtra coches por estado y conserva el filtro en la paginación', function () {
    // No se fijan IDs: en PostgreSQL la secuencia no retrocede con el rollback
    // de RefreshDatabase, así que los coches sembrados no empiezan en 1.
    $ids = Car::query()->orderBy('id')->limit(2)->pluck('id')->all();
    expect($ids)->toHaveCount(2);

    Car::query()->whereIn('id', $ids)->update(['status' => CarStatus::RESERVED->value]);

    $response = $this->getJson('/api/cars?status=reserved&per_page=1')->assertOk();

    expect($response->json('data'))->not->toBeEmpty()
        ->and((string) $response->json('links.next'))->toContain('status=reserved');

    foreach ($response->json('data') as $car) {
        expect($car['status'])->toBe(CarStatus::RESERVED->value);
    }
});

it('filtra coches por sucursal, color, tipo, categoría y modelo', function () {
    $car = Car::query()->orderBy('id')->firstOrFail();

    $response = $this->getJson('/api/cars?'.http_build_query([
        'branch_id' => $car->branch_id,
        'color_id' => $car->color_id,
        'car_type_id' => $car->car_type_id,
        'category_id' => $car->category_id,
        'car_model_id' => $car->car_model_id,
        'per_page' => 100,
    ]))->assertOk();

    expect($response->json('data'))->not->toBeEmpty();

    foreach ($response->json('data') as $item) {
        expect($item['branch_id'])->toBe((int) $car->branch_id)
            ->and($item['color_id'])->toBe((int) $car->color_id)
            ->and($item['car_type_id'])->toBe((int) $car->car_type_id)
            ->and($item['category_id'])->toBe((int) $car->category_id)
            ->and($item['car_model_id'])->toBe((int) $car->car_model_id);
    }
});

it('filtra coches por marca a través del modelo', function () {
    $corolla = CarModel::where('name', 'Corolla')->firstOrFail();
    $modelIds = CarModel::where('brand_id', $corolla->brand_id)->pluck('id')->all();

    $response = $this->getJson('/api/cars?brand_id='.$corolla->brand_id.'&per_page=100')->assertOk();

    expect($response->json('data'))->not->toBeEmpty();

    foreach ($response->json('data') as $car) {
        expect(in_array($car['car_model_id'], $modelIds, true))->toBeTrue();
    }
});

it('filtra coches por año y precio', function () {
    $response = $this->getJson('/api/cars?year_min=2026&price_min=25000&per_page=100')->assertOk();

    expect($response->json('data'))->not->toBeEmpty();

    foreach ($response->json('data') as $car) {
        expect((int) $car['year'])->toBeGreaterThanOrEqual(2026)
            ->and((float) $car['price'])->toBeGreaterThanOrEqual(25000);
    }
});

it('busca por prefijo del VIN sin tratar los comodines como comodines', function () {
    $car = Car::where('vin', '01234567890123457')->firstOrFail();

    $found = $this->getJson('/api/cars?search='.$car->vin)->assertOk();
    expect(array_column($found->json('data'), 'id'))->toBe([$car->id]);

    $none = $this->getJson('/api/cars?search=%25')->assertOk();
    expect($none->json('meta.total'))->toBe(0);
});

it('rechaza filtros no válidos', function (string $query) {
    $this->getJson('/api/cars?'.$query)->assertUnprocessable();
})->with([
    'status=desconocido',
    'branch_id=999999',
    'brand_id=no-es-enter',
    'year_min=1800',
    'price_min=-5',
]);
