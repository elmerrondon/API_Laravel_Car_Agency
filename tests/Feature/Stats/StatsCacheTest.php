<?php

declare(strict_types=1);

use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Tax;
use App\Models\Users\User;
use App\Services\Stats\StatsCache;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    Sanctum::actingAs($this->admin);
});

/**
 * Payload válido de venta para el alta por API.
 *
 * @return array<string, mixed>
 */
function statsSalePayload(Car $car): array
{
    return [
        'discount_percentage' => 0,
        'exchange_rate' => 1,
        'taxes' => [Tax::where('code', 'I0001')->value('id')],
        'sale_date' => '2026-02-10 10:30:00',
        'car_id' => $car->id,
        'branch_id' => $car->branch_id,
        'payment_currency_id' => Currency::where('code', 'EUR')->value('id'),
        'payment_method_id' => PaymentMethod::firstOrFail()->id,
    ];
}

it('reutiliza el valor cacheado y separa las claves por endpoint y filtro', function () {
    $cache = app(StatsCache::class);
    $calls = 0;
    $callback = function () use (&$calls): array {
        $calls++;

        return ['calls' => $calls];
    };

    expect($cache->remember('summary', ['branch_id' => null], $callback))->toBe(['calls' => 1])
        ->and($cache->remember('summary', ['branch_id' => null], $callback))->toBe(['calls' => 1])
        ->and($cache->remember('summary', ['branch_id' => 1], $callback))->toBe(['calls' => 2])
        ->and($cache->remember('top-users', ['branch_id' => null], $callback))->toBe(['calls' => 3]);

    // `flush()` cambia la versión y obliga a recalcular todas las claves.
    $cache->flush();

    expect($cache->remember('summary', ['branch_id' => null], $callback))->toBe(['calls' => 4])
        ->and($cache->remember('top-users', ['branch_id' => null], $callback))->toBe(['calls' => 5]);
});

it('desactiva la caché con un TTL de cero', function () {
    config(['stats.cache_ttl' => 0]);

    $cache = app(StatsCache::class);
    $calls = 0;
    $callback = function () use (&$calls): int {
        return ++$calls;
    };

    expect($cache->remember('summary', [], $callback))->toBe(1)
        ->and($cache->remember('summary', [], $callback))->toBe(2);
});

it('invalida los ocho agregados al crear o cancelar una venta', function (string $endpoint) {
    $branch = Branch::firstOrFail();
    $cars = Car::query()->where('status', 'available')->orderBy('id')->take(2)->get();

    $initial = $this->getJson($endpoint)->assertOk()->json();

    // Un alta directa (sin SaleService) no incrementa la versión: la siguiente
    // petición debe seguir sirviendo el valor cacheado.
    createSaleRecord($cars[0], $this->admin, $branch, '2026-02-01 10:00:00', 100);

    expect($this->getJson($endpoint)->assertOk()->json())->toBe($initial);

    // El alta por API invalida la caché de todos los agregados, no solo del
    // resumen que se consultó para generar la versión.
    $saleId = $this->postJson('/api/sales', statsSalePayload($cars[1]))
        ->assertCreated()
        ->json('data.id');

    $afterCreate = $this->getJson($endpoint)->assertOk()->json();

    expect($afterCreate)->not->toBe($initial);

    // Cancelar también invalida: la venta cancelada deja de contar.
    $this->patchJson("/api/sales/{$saleId}/cancel", ['notes' => 'Prueba de caché'])
        ->assertOk();

    expect($this->getJson($endpoint)->assertOk()->json())->not->toBe($afterCreate);
})->with([
    '/api/stats/summary',
    '/api/stats/top-users',
    '/api/stats/top-branches',
    '/api/stats/top-cities',
    '/api/stats/top-brands',
    '/api/stats/top-models',
    '/api/stats/inventory',
    '/api/stats/sales/trend',
]);

it('caduca por TTL en los ocho agregados sin que cambie la versión', function (string $endpoint) {
    config(['stats.cache_ttl' => 10]);

    $branch = Branch::firstOrFail();
    $car = Car::query()->where('status', 'available')->orderBy('id')->firstOrFail();

    $initial = $this->getJson($endpoint)->assertOk()->json();

    // Mutación directa en base de datos: no pasa por SaleService, así que no
    // incrementa `stats:version`. Debe seguir sirviéndose el valor cacheado.
    createSaleRecord($car, $this->admin, $branch, '2026-02-01 10:00:00', 100);
    $car->update(['status' => 'sold', 'price' => $car->price + 1]);

    expect($this->getJson($endpoint)->assertOk()->json())->toBe($initial);

    $this->travel(11)->seconds();

    expect($this->getJson($endpoint)->assertOk()->json())->not->toBe($initial);

    $this->travelBack();
})->with([
    '/api/stats/summary',
    '/api/stats/top-users',
    '/api/stats/top-branches',
    '/api/stats/top-cities',
    '/api/stats/top-brands',
    '/api/stats/top-models',
    '/api/stats/inventory',
    '/api/stats/sales/trend',
]);
