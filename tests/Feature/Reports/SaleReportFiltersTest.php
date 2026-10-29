<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Users\User;
use App\Services\Reports\SaleReportService;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());
});

/**
 * Crea una venta con importes ya calculados para probar los totales del reporte.
 */
function reportFilterSale(
    string $saleDate,
    int $branchId,
    float $baseAmount,
    ?float $paidAmount = null,
    float $taxes = 0,
    float $discount = 0,
): Sale {
    $car = Car::query()->inRandomOrder()->firstOrFail();
    $user = User::where('email', 'manager@caragency.test')->firstOrFail();

    return Sale::create([
        'exchange_rate' => 1,
        'base_price' => $baseAmount,
        'taxes' => [],
        'total_taxes' => $taxes,
        'discount_percentage' => 0,
        'total_base_amount' => $baseAmount,
        'total_amount_paid' => $paidAmount ?? $baseAmount,
        'total_discount' => $discount,
        'status' => SaleStatus::COMPLETED,
        'sale_date' => $saleDate,
        'car_id' => $car->id,
        'user_id' => $user->id,
        'branch_id' => $branchId,
        'payment_currency_id' => Currency::where('is_base', true)->firstOrFail()->id,
        'base_currency_id' => Currency::where('is_base', true)->firstOrFail()->id,
        'payment_method_id' => PaymentMethod::firstOrFail()->id,
    ]);
}

it('exige from y to juntos o ninguno', function () {
    $this->getJson('/api/reports/sales/pdf?to=2026-02-28')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from');

    $this->getJson('/api/reports/sales/pdf?from=2026-02-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');

    $this->getJson('/api/reports/sales/pdf')->assertOk();
});

it('rechaza fechas inválidas o invertidas', function () {
    $this->getJson('/api/reports/sales/pdf?from=02/01/2026&to=2026-02-28')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('from');

    $this->getJson('/api/reports/sales/pdf?from=2026-02-01&to=2026-02-30')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');

    $this->getJson('/api/reports/sales/pdf?from=2026-02-10&to=2026-02-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('rechaza una sucursal inexistente', function () {
    $this->getJson('/api/reports/sales/pdf?branch_id=999999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('branch_id');
});

it('incluye los extremos del rango y excluye lo que queda fuera', function () {
    $branch = Branch::firstOrFail();

    $first = reportFilterSale('2026-02-01 00:00:00', $branch->id, 100);
    $middle = reportFilterSale('2026-02-15 12:30:00', $branch->id, 200);
    $last = reportFilterSale('2026-02-28 23:59:59', $branch->id, 300);
    reportFilterSale('2026-01-31 23:59:59', $branch->id, 400);
    reportFilterSale('2026-03-01 00:00:00', $branch->id, 500);

    $report = app(SaleReportService::class)->build([
        'from' => '2026-02-01',
        'to' => '2026-02-28',
    ]);

    expect($report['sales']->pluck('id')->all())->toBe([$first->id, $middle->id, $last->id])
        ->and($report['filters'])->toBe([
            'from' => '2026-02-01',
            'to' => '2026-02-28',
            'branch_id' => null,
        ])
        ->and($report['summary']['count'])->toBe(3);
});

it('suma los totales en moneda base y no en la moneda de pago', function () {
    $branch = Branch::firstOrFail();

    reportFilterSale('2026-02-05 10:00:00', $branch->id, 1000.00, 1100.00, 100.00, 50.00);
    reportFilterSale('2026-02-06 10:00:00', $branch->id, 500.25, 500.25, 25.25, 0);

    $report = app(SaleReportService::class)->build([
        'from' => '2026-02-01',
        'to' => '2026-02-28',
    ]);

    expect($report['summary'])->toBe([
        'count' => 2,
        'total_base_amount' => 1500.25,
        'total_taxes' => 125.25,
        'total_discount' => 50.0,
    ])
        ->and($report['base_currency'])->toBe(Currency::where('is_base', true)->value('code'));
});

it('normaliza el branch_id que llega como string desde la query string', function () {
    $branchA = Branch::firstOrFail();
    $branchB = Branch::whereKeyNot($branchA->id)->firstOrFail();

    $expected = reportFilterSale('2026-02-05 10:00:00', $branchA->id, 1000);
    reportFilterSale('2026-02-06 10:00:00', $branchB->id, 500);

    $report = app(SaleReportService::class)->build(['branch_id' => (string) $branchA->id]);

    expect($report['filters']['branch_id'])->toBe($branchA->id)
        ->and($report['sales']->pluck('id')->all())->toBe([$expected->id])
        ->and($report['branch']?->id)->toBe($branchA->id);
});

it('devuelve todo el historial cuando no hay filtros', function () {
    $branch = Branch::firstOrFail();

    reportFilterSale('2026-02-05 10:00:00', $branch->id, 1000);
    reportFilterSale('2025-11-05 10:00:00', $branch->id, 500);

    $report = app(SaleReportService::class)->build([]);

    expect($report['summary']['count'])->toBe(2)
        ->and($report['summary']['total_base_amount'])->toBe(1500.0)
        ->and($report['filters']['branch_id'])->toBeNull()
        ->and($report['branch'])->toBeNull();
});
