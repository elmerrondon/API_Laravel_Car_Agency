<?php

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
});

/**
 * Crea una venta con fecha, sucursal y monto conocidos para los reportes.
 */
function salesReportSale(string $saleDate, int $branchId, float $amount, SaleStatus $status = SaleStatus::COMPLETED): Sale
{
    $car = Car::query()->inRandomOrder()->firstOrFail();
    $user = User::where('email', 'manager@caragency.test')->firstOrFail();
    $baseCurrency = Currency::where('is_base', true)->firstOrFail();
    $paymentCurrency = Currency::where('code', 'EUR')->firstOrFail();
    $paymentMethod = PaymentMethod::firstOrFail();

    return Sale::create([
        'exchange_rate' => 1,
        'base_price' => $amount,
        'taxes' => [],
        'total_taxes' => 0,
        'discount_percentage' => 0,
        'total_base_amount' => $amount,
        'total_amount_paid' => $amount,
        'total_discount' => 0,
        'status' => $status,
        'sale_date' => $saleDate,
        'car_id' => $car->id,
        'user_id' => $user->id,
        'branch_id' => $branchId,
        'payment_currency_id' => $paymentCurrency->id,
        'base_currency_id' => $baseCurrency->id,
        'payment_method_id' => $paymentMethod->id,
    ]);
}

it('exige autenticación para el reporte de ventas', function () {
    $this->getJson('/api/reports/sales/pdf')->assertUnauthorized();
});

it('rechaza el reporte de ventas sin el permiso view_reports', function () {
    Sanctum::actingAs(User::where('email', 'vendedor@caragency.test')->firstOrFail());

    $this->getJson('/api/reports/sales/pdf')->assertForbidden();
});

it('descarga el reporte de ventas con cabecera PDF', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());
    salesReportSale('2026-02-10 12:00:00', Branch::firstOrFail()->id, 1500.50);

    $response = $this->get('/api/reports/sales/pdf');

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('filtra las ventas por rango de fechas y sucursal', function () {
    $branchA = Branch::firstOrFail();
    $branchB = Branch::where('id', '!=', $branchA->id)->firstOrFail();

    salesReportSale('2026-01-10 10:00:00', $branchA->id, 100);
    $expected = salesReportSale('2026-02-15 10:00:00', $branchA->id, 200);
    salesReportSale('2026-02-20 10:00:00', $branchB->id, 300);

    $report = app(SaleReportService::class)->build([
        'from' => '2026-02-01',
        'to' => '2026-02-28',
        'branch_id' => $branchA->id,
    ]);

    expect($report['summary']['count'])->toBe(1)
        ->and($report['summary']['total_base_amount'])->toBe(200.0)
        ->and($report['sales']->pluck('id')->all())->toBe([$expected->id])
        ->and($report['branch']->id)->toBe($branchA->id)
        ->and($report['base_currency'])->toBe('USD');
});

it('valida el rango de fechas del reporte de ventas', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());

    $this->getJson('/api/reports/sales/pdf?from=2026-02-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');

    $this->getJson('/api/reports/sales/pdf?from=2026-02-10&to=2026-02-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('incluye las ventas y los totales en la vista del reporte', function () {
    $sale = salesReportSale('2026-02-10 12:00:00', Branch::firstOrFail()->id, 1500.50);
    $brand = $sale->car->carModel->brand->name;

    $report = app(SaleReportService::class)->build([
        'from' => '2026-02-01',
        'to' => '2026-02-28',
    ]);

    $html = view('reports.sales-pdf', $report)->render();

    expect($html)->toContain('Reporte de ventas')
        ->toContain($brand)
        ->toContain('1,500.50')
        ->toContain('USD');
});
