<?php

declare(strict_types=1);

use App\Models\Cars\Car;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Tax;
use App\Models\Users\User;
use App\Services\Reports\SaleReportService;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);
});

/**
 * Crea un coche, lo vende y devuelve ambos modelos.
 *
 * @return array{car: Car, sale_id: int}
 */
function soldCarForHistory(): array
{
    $source = Car::firstOrFail();
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    $car = test()->postJson('/api/cars', [
        'price' => 25000,
        'mileage' => 120,
        'year' => 2025,
        'vin' => '1HGCM82633A004356',
        'status' => 'available',
        'color_id' => $source->color_id,
        'car_model_id' => $source->car_model_id,
        'car_type_id' => $source->car_type_id,
        'category_id' => $source->category_id,
        'branch_id' => $source->branch_id,
    ])->assertCreated()->json('data');

    $sale = test()->postJson('/api/sales', [
        'discount_percentage' => 0,
        'exchange_rate' => 1,
        'taxes' => [Tax::where('code', 'I0001')->value('id')],
        'sale_date' => '2026-02-10 10:30:00',
        'car_id' => $car['id'],
        'user_id' => $admin->id,
        'branch_id' => $car['branch_id'],
        'payment_currency_id' => Currency::where('code', 'EUR')->value('id'),
        'payment_method_id' => PaymentMethod::firstOrFail()->id,
    ])->assertCreated()->json('data');

    return ['car' => Car::findOrFail($car['id']), 'sale_id' => $sale['id']];
}

it('conserva el coche, la marca y el modelo en el detalle de una venta tras el soft delete', function () {
    ['car' => $car, 'sale_id' => $saleId] = soldCarForHistory();

    $this->deleteJson("/api/cars/{$car->id}")->assertNoContent();

    expect(Car::withTrashed()->findOrFail($car->id)->trashed())->toBeTrue();

    $this->getJson("/api/sales/{$saleId}")
        ->assertOk()
        ->assertJsonPath('data.car.id', $car->id)
        ->assertJsonPath('data.car.vin', $car->vin)
        ->assertJsonPath('data.car.car_model.brand.name', $car->carModel->brand->name)
        ->assertJsonPath('data.car.car_model.name', $car->carModel->name);
});

it('mantiene el coche en el reporte de ventas tras el soft delete', function () {
    ['car' => $car, 'sale_id' => $saleId] = soldCarForHistory();

    $this->deleteJson("/api/cars/{$car->id}")->assertNoContent();

    $report = app(SaleReportService::class)->build([
        'from' => '2026-02-01',
        'to' => '2026-02-28',
    ]);

    $sale = $report['sales']->firstWhere('id', $saleId);

    expect($sale)->not->toBeNull()
        ->and($sale->car)->not->toBeNull()
        ->and($sale->car->id)->toBe($car->id)
        ->and($sale->car->carModel->brand->name)->toBe($car->carModel->brand->name)
        ->and($sale->car->carModel->name)->toBe($car->carModel->name);
});
