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
 * Comprueba que el listado no añade consultas al crecer la página.
 */
function assertConstantQueriesFor(string $url, int $smallPage, int $largePage): void
{
    $small = countQueriesDuringRequest(function () use ($url, $smallPage) {
        test()->getJson("{$url}?per_page={$smallPage}")->assertOk();
    });

    $large = countQueriesDuringRequest(function () use ($url, $largePage) {
        test()->getJson("{$url}?per_page={$largePage}")->assertOk();
    });

    expect($large)->toBe($small);
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($admin);

    // La primera comprobación de permisos calienta la caché; sin este
    // warm-up el primer request del test pagaría el join extra y la
    // comparación entre páginas dejaría de ser simétrica.
    $admin->hasPermissionTo(PermissionEnum::VIEW_CARS);
});

it('mantiene constante el número de consultas del listado de coches', function () {
    assertConstantQueriesFor('/api/cars', 5, 50);
});

it('mantiene constante el número de consultas del listado de ventas', function () {
    seedSales(20);

    assertConstantQueriesFor('/api/sales', 5, 20);
});

it('mantiene constante el número de consultas de los catálogos', function (string $url) {
    assertConstantQueriesFor($url, 1, 40);
})->with([
    '/api/brands',
    '/api/colors',
    '/api/categories',
    '/api/car-types',
    '/api/car-models',
    '/api/taxes',
    '/api/currencies',
    '/api/payment-methods',
]);

it('mantiene constante el número de consultas de las ubicaciones', function (string $url, int $large) {
    assertConstantQueriesFor($url, 1, $large);
})->with([
    ['/api/branches', 8],
    ['/api/cities', 50],
    ['/api/states', 50],
    ['/api/countries', 50],
]);

it('mantiene constante el número de consultas del listado de usuarios', function () {
    assertConstantQueriesFor('/api/users', 1, 3);
});

it('no escala las consultas de los reportes de coches con el número de filas', function () {
    $small = countQueriesDuringRequest(function () {
        $this->getJson('/api/reports/cars')->assertOk();
    });

    Car::factory()->count(100)->create();

    $large = countQueriesDuringRequest(function () {
        $this->getJson('/api/reports/cars')->assertOk();
    });

    $branchId = Branch::firstOrFail()->id;

    $filtered = countQueriesDuringRequest(function () use ($branchId) {
        $this->getJson("/api/reports/cars?branch_id={$branchId}")->assertOk();
    });

    $pdf = countQueriesDuringRequest(function () use ($branchId) {
        $this->get("/api/reports/cars/pdf?branch_id={$branchId}")->assertOk();
    });

    // El agregado vive en SQL: 100 coches más no añaden consultas.
    expect($large)->toBe($small)
        // El PDF reutiliza el mismo servicio y no añade consultas.
        ->and($pdf)->toBe($filtered);
});

it('no escala las consultas del reporte de ventas con el número de ventas', function () {
    seedSales(5);

    $small = countQueriesDuringRequest(function () {
        $this->get('/api/reports/sales/pdf')->assertOk();
    });

    seedSales(15);

    $large = countQueriesDuringRequest(function () {
        $this->get('/api/reports/sales/pdf')->assertOk();
    });

    expect($large)->toBe($small);
});
