<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());
});

/**
 * Crea una venta mínima para engordar el reporte.
 */
function limitSale(int $branchId, string $saleDate = '2026-02-10 10:00:00'): Sale
{
    $car = Car::query()->inRandomOrder()->firstOrFail();
    $user = User::where('email', 'manager@caragency.test')->firstOrFail();
    $base = Currency::where('is_base', true)->firstOrFail();

    return Sale::create([
        'exchange_rate' => 1,
        'base_price' => 1000,
        'taxes' => [],
        'total_taxes' => 0,
        'discount_percentage' => 0,
        'total_base_amount' => 1000,
        'total_amount_paid' => 1000,
        'total_discount' => 0,
        'status' => SaleStatus::COMPLETED,
        'sale_date' => $saleDate,
        'car_id' => $car->id,
        'user_id' => $user->id,
        'branch_id' => $branchId,
        'payment_currency_id' => $base->id,
        'base_currency_id' => $base->id,
        'payment_method_id' => PaymentMethod::firstOrFail()->id,
    ]);
}

it('rechaza el reporte de ventas cuando supera el límite de filas', function () {
    config(['reports.max_rows' => 2]);

    $branch = Branch::firstOrFail();

    foreach (range(1, 3) as $index) {
        limitSale($branch->id, "2026-02-0{$index} 10:00:00");
    }

    $response = $this->getJson('/api/reports/sales/pdf')
        ->assertStatus(422);

    expect($response->json('message'))
        ->toContain('3 sales')
        ->toContain('limit is 2')
        ->toContain('Narrow the date range');

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('permite generar el reporte justo en el límite', function () {
    config(['reports.max_rows' => 3]);

    $branch = Branch::firstOrFail();

    foreach (range(1, 3) as $index) {
        limitSale($branch->id, "2026-02-0{$index} 10:00:00");
    }

    $response = $this->get('/api/reports/sales/pdf')->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('desactiva el límite con un valor de cero', function () {
    config(['reports.max_rows' => 0]);

    $branch = Branch::firstOrFail();

    foreach (range(1, 10) as $index) {
        limitSale($branch->id, sprintf('2026-02-%02d 10:00:00', $index));
    }

    $this->get('/api/reports/sales/pdf')->assertOk();
});

it('deja pasar el reporte si el filtro por sucursal baja del límite', function () {
    config(['reports.max_rows' => 2]);

    $branchA = Branch::firstOrFail();
    $branchB = Branch::whereKeyNot($branchA->id)->firstOrFail();

    limitSale($branchA->id, '2026-02-01 10:00:00');
    limitSale($branchA->id, '2026-02-02 10:00:00');
    limitSale($branchB->id, '2026-02-03 10:00:00');
    limitSale($branchB->id, '2026-02-04 10:00:00');

    $this->get('/api/reports/sales/pdf')->assertStatus(422);

    $this->get("/api/reports/sales/pdf?branch_id={$branchA->id}")->assertOk();
});

it('no aplica el límite al reporte de coches, que agrega en SQL', function () {
    config(['reports.max_rows' => 1]);

    expect(Car::count())->toBeGreaterThan(1);

    $this->getJson('/api/reports/cars')->assertOk();

    $response = $this->get('/api/reports/cars/pdf')->assertOk();

    expect($response->getContent())->toStartWith('%PDF');
});
