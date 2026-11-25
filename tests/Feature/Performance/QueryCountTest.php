<?php

declare(strict_types=1);

use App\Enums\Users\PermissionEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Ejecuta la petición y devuelve cuántas consultas SQL dispara.
 */
function countQueriesDuringRequest(Closure $callback): int
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

/**
 * Fija el número exacto de consultas de un listado.
 *
 * Comparar páginas entre sí no detecta un coste constante nuevo (un `+2` en
 * todas las páginas pasaba) ni la pérdida de un eager load: con `whenLoaded()`
 * la relación desaparece del JSON pero la consulta que ya no se hace tampoco
 * cambia el total. Por eso el valor es absoluto y el test de estructura
 * comprueba que las relaciones siguen presentes.
 */
function assertQueriesFor(string $url, int $expected, bool $json = true): void
{
    $count = countQueriesDuringRequest(function () use ($url, $json) {
        $json
            ? test()->getJson($url)->assertOk()
            : test()->get($url)->assertOk();
    });

    expect($count)->toBe($expected, "{$url} ejecutó {$count} consultas; se esperaban {$expected}");
}

/**
 * Comprueba el valor absoluto en una página pequeña y en una grande.
 */
function assertConstantQueriesFor(string $url, int $smallPage, int $largePage, int $expected): void
{
    assertQueriesFor("{$url}?per_page={$smallPage}", $expected);
    assertQueriesFor("{$url}?per_page={$largePage}", $expected);
}

/**
 * Falla si alguna de las rutas anidadas no llegó al JSON.
 *
 * @param  array<string, mixed>  $item
 * @param  list<string>  $paths
 */
function assertRelationsPresent(array $item, array $paths): void
{
    foreach ($paths as $path) {
        expect(data_get($item, $path))->not->toBeNull("Falta la relación {$path}");
    }
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($admin);

    // La primera comprobación de permisos calienta la caché; sin este
    // warm-up el primer request del test pagaría el join extra.
    $admin->hasPermissionTo(PermissionEnum::VIEW_CARS);
});

it('mantiene constante el número de consultas del listado de coches', function () {
    assertConstantQueriesFor('/api/cars', 5, 50, 11);

    $first = $this->getJson('/api/cars?per_page=1')->assertOk()->json('data.0');

    assertRelationsPresent($first, [
        'color',
        'car_model.brand',
        'car_type',
        'category',
        'branch.city.state.country',
    ]);
});

it('mantiene constante el número de consultas del listado de ventas', function () {
    seedSales(20);

    assertConstantQueriesFor('/api/sales', 5, 20, 22);

    $first = $this->getJson('/api/sales?per_page=1')->assertOk()->json('data.0');

    assertRelationsPresent($first, [
        'car.color',
        'car.car_model.brand',
        'car.car_type',
        'car.category',
        'car.branch.city.state.country',
        'branch.city.state.country',
        'user.roles',
        'user.branches',
        'payment_currency',
        'base_currency',
        'payment_method',
    ]);
});

it('mantiene constante el número de consultas de los catálogos', function (string $url, int $expected) {
    assertConstantQueriesFor($url, 1, 40, $expected);
})->with([
    ['/api/brands', 2],
    ['/api/colors', 2],
    ['/api/categories', 2],
    ['/api/car-types', 2],
    // El modelo carga su marca.
    ['/api/car-models', 3],
    ['/api/taxes', 2],
    ['/api/currencies', 2],
    ['/api/payment-methods', 2],
]);

it('mantiene constante el número de consultas de las ubicaciones', function (string $url, int $large, int $expected) {
    assertConstantQueriesFor($url, 1, $large, $expected);
})->with([
    ['/api/branches', 8, 5],
    ['/api/cities', 50, 4],
    ['/api/states', 50, 3],
    ['/api/countries', 50, 2],
]);

it('mantiene constante el número de consultas del listado de usuarios', function () {
    assertConstantQueriesFor('/api/users', 1, 3, 8);

    $first = $this->getJson('/api/users?per_page=1')->assertOk()->json('data.0');

    assertRelationsPresent($first, ['roles.0.name', 'branches.0.city']);
});

it('no escala las consultas de los reportes de coches con el número de filas', function () {
    assertQueriesFor('/api/reports/cars', 4);

    Car::factory()->count(100)->create();

    assertQueriesFor('/api/reports/cars', 4);

    $branchId = Branch::firstOrFail()->id;

    // El filtro añade la sucursal y la moneda base, no una consulta por fila.
    assertQueriesFor("/api/reports/cars?branch_id={$branchId}", 6);

    // El PDF reutiliza el mismo servicio y no añade consultas.
    assertQueriesFor("/api/reports/cars/pdf?branch_id={$branchId}", 6, json: false);
});

it('no escala las consultas del reporte de ventas con el número de ventas', function () {
    seedSales(5);

    assertQueriesFor('/api/reports/sales/pdf', 12, json: false);

    seedSales(15);

    assertQueriesFor('/api/reports/sales/pdf', 12, json: false);

    $branchId = Branch::firstOrFail()->id;

    assertQueriesFor("/api/reports/sales/pdf?branch_id={$branchId}", 14, json: false);
});

it('no escala las consultas de las estadísticas con el número de ventas', function () {
    // Sin caché: el objetivo es medir las consultas de los agregados. Con la
    // caché activa, la segunda petición sería un acierto y no ejecutaría SQL.
    config(['stats.cache_ttl' => 0]);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    // Calienta la caché de permisos de `view_statistics`.
    $admin->hasPermissionTo(PermissionEnum::VIEW_STATISTICS);

    $endpoints = [
        // El resumen suma varios agregados en consultas separadas.
        '/api/stats/summary' => 5,
        '/api/stats/top-users' => 2,
        '/api/stats/top-branches' => 2,
        '/api/stats/top-cities' => 2,
        '/api/stats/top-brands' => 2,
        '/api/stats/top-models' => 2,
        '/api/stats/inventory' => 3,
        '/api/stats/sales/trend' => 2,
    ];

    foreach ($endpoints as $endpoint => $expected) {
        seedSales(5);

        assertQueriesFor($endpoint, $expected);

        seedSales(20);

        assertQueriesFor($endpoint, $expected);
    }
});
