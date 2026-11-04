<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
});

it('exige autenticación para consultar el resumen', function () {
    $this->getJson('/api/stats/summary')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/summary')->assertForbidden();
});

it('permite el resumen a un manager', function () {
    actingAsRole(RoleEnum::MANAGER);

    $this->getJson('/api/stats/summary')->assertOk();
});

it('calcula los kpis solo con ventas completadas', function () {
    Sanctum::actingAs($this->admin);

    $cars = Car::query()->orderBy('id')->take(3)->get();
    $branch = Branch::firstOrFail();

    createSaleRecord($cars[0], $this->admin, $branch, '2026-01-15 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branch, '2026-02-15 10:00:00', 200.5);
    createSaleRecord($cars[2], $this->admin, $branch, '2026-03-15 10:00:00', 999, SaleStatus::CANCELLED);

    $this->getJson('/api/stats/summary')
        ->assertOk()
        ->assertJsonPath('sales.completed_count', 2)
        ->assertJsonPath('sales.total_base_amount', 300.5)
        ->assertJsonPath('sales.average_ticket', 150.25)
        ->assertJsonPath('base_currency', 'USD')
        ->assertJsonPath('meta.start_date', null)
        ->assertJsonPath('meta.end_date', null)
        ->assertJsonPath('meta.branch_id', null);
});

it('incluye el inventario actual y la estructura activa', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/stats/summary')->assertOk();

    $byStatus = collect($response->json('inventory.by_status'));

    expect($response->json('inventory.total_cars'))->toBe(Car::count())
        ->and($byStatus->pluck('status')->all())->toBe(['available', 'reserved', 'sold'])
        ->and($byStatus->sum('count'))->toBe(Car::count())
        ->and($response->json('active_users'))->toBe(3)
        ->and($response->json('active_branches'))->toBe(8);
});

it('filtra los kpis por rango de fechas inclusivo', function () {
    Sanctum::actingAs($this->admin);

    $cars = Car::query()->orderBy('id')->take(4)->get();
    $branch = Branch::firstOrFail();

    createSaleRecord($cars[0], $this->admin, $branch, '2026-01-31 23:59:59', 10);
    createSaleRecord($cars[1], $this->admin, $branch, '2026-02-01 00:00:00', 20);
    createSaleRecord($cars[2], $this->admin, $branch, '2026-02-28 23:59:59', 30);
    createSaleRecord($cars[3], $this->admin, $branch, '2026-03-01 00:00:00', 40);

    $this->getJson('/api/stats/summary?start_date=2026-02-01&end_date=2026-02-28')
        ->assertOk()
        ->assertJsonPath('sales.completed_count', 2)
        ->assertJsonPath('sales.total_base_amount', 50)
        ->assertJsonPath('meta.start_date', '2026-02-01')
        ->assertJsonPath('meta.end_date', '2026-02-28');
});

it('filtra los kpis por sucursal', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()->orderBy('id')->take(2)->get();
    $cars = Car::query()->orderBy('id')->take(2)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[1], '2026-01-10 10:00:00', 250);

    $this->getJson('/api/stats/summary?branch_id='.$branches[1]->id)
        ->assertOk()
        ->assertJsonPath('sales.completed_count', 1)
        ->assertJsonPath('sales.total_base_amount', 250)
        ->assertJsonPath('meta.branch_id', $branches[1]->id);
});

it('devuelve cero en el ticket promedio cuando no hay ventas', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/stats/summary?start_date=2030-01-01&end_date=2030-12-31')
        ->assertOk()
        ->assertJsonPath('sales.completed_count', 0)
        ->assertJsonPath('sales.total_base_amount', 0)
        ->assertJsonPath('sales.average_ticket', 0);
});

it('valida las fechas y la sucursal', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/stats/summary?start_date=2026-01-01')->assertUnprocessable();
    $this->getJson('/api/stats/summary?end_date=2026-01-31')->assertUnprocessable();
    $this->getJson('/api/stats/summary?start_date=2026-02-01&end_date=2026-01-01')->assertUnprocessable();
    $this->getJson('/api/stats/summary?branch_id=999999')->assertUnprocessable();
});
