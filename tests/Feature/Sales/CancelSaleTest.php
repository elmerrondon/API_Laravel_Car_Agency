<?php

use App\DTOs\Sales\SaleData;
use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Exceptions\Sales\CarNotAvailableException;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use App\Models\Users\User;
use App\Services\Sales\SaleService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

/**
 * Crea una venta con el estado indicado y ajusta el estado del coche en consecuencia.
 */
function createSaleWithStatus(SaleStatus $status = SaleStatus::COMPLETED): Sale
{
    $car = Car::where('status', CarStatus::AVAILABLE)->firstOrFail();
    $branch = Branch::firstOrFail();
    $baseCurrency = Currency::where('is_base', true)->firstOrFail();
    $paymentCurrency = Currency::where('code', 'EUR')->firstOrFail();
    $paymentMethod = PaymentMethod::firstOrFail();

    $user = User::create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'document_number' => 'DOC10001',
        'code' => 'USR-001',
        'email' => 'ana@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $car->update([
        'status' => $status === SaleStatus::COMPLETED ? CarStatus::SOLD : CarStatus::RESERVED,
    ]);

    return Sale::create([
        'exchange_rate' => 1,
        'base_price' => $car->price,
        'taxes' => [],
        'total_taxes' => 0,
        'discount_percentage' => 0,
        'total_base_amount' => $car->price,
        'total_amount_paid' => $car->price,
        'total_discount' => 0,
        'status' => $status,
        'sale_date' => now(),
        'car_id' => $car->id,
        'user_id' => $user->id,
        'branch_id' => $branch->id,
        'payment_currency_id' => $paymentCurrency->id,
        'base_currency_id' => $baseCurrency->id,
        'payment_method_id' => $paymentMethod->id,
    ]);
}

it('cancela una venta completada y libera el coche', function () {
    $sale = createSaleWithStatus();

    $response = $this->patchJson("/api/sales/{$sale->id}/cancel", [
        'notes' => 'El cliente desistió de la compra',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', SaleStatus::CANCELLED->value)
        ->assertJsonPath('data.notes', 'El cliente desistió de la compra');

    expect($sale->fresh()->status)->toBe(SaleStatus::CANCELLED)
        ->and($sale->car->fresh()->status)->toBe(CarStatus::AVAILABLE);
});

it('rechaza cancelar dos veces la misma venta con 409', function () {
    $sale = createSaleWithStatus();

    $this->patchJson("/api/sales/{$sale->id}/cancel", [
        'notes' => 'El cliente desistió de la compra',
    ])->assertOk();

    $response = $this->patchJson("/api/sales/{$sale->id}/cancel", [
        'notes' => 'El cliente desistió de la compra',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'The sale has already been cancelled');

    expect($sale->fresh()->status)->toBe(SaleStatus::CANCELLED)
        ->and($sale->car->fresh()->status)->toBe(CarStatus::AVAILABLE);
});

it('rechaza cancelar una venta que no está completada con 409', function () {
    $sale = createSaleWithStatus(SaleStatus::PENDING);

    $response = $this->patchJson("/api/sales/{$sale->id}/cancel", [
        'notes' => 'El cliente desistió de la compra',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Only completed sales can be cancelled, current status: pending');

    expect($sale->fresh()->status)->toBe(SaleStatus::PENDING)
        ->and($sale->car->fresh()->status)->toBe(CarStatus::RESERVED);
});

it('rechaza vender un coche que ya está vendido con 422', function () {
    $sale = createSaleWithStatus();

    $response = $this->postJson('/api/sales', [
        'discount_percentage' => 0,
        'exchange_rate' => 1,
        'taxes' => [Tax::where('is_active', true)->value('id')],
        'sale_date' => now()->format('Y-m-d H:i:s'),
        'car_id' => $sale->car_id,
        'user_id' => $sale->user_id,
        'branch_id' => $sale->branch_id,
        'payment_currency_id' => $sale->payment_currency_id,
        'payment_method_id' => $sale->payment_method_id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('car_id');
});

it('rechaza vender un coche reservado desde el servicio con 422', function () {
    $sale = createSaleWithStatus(SaleStatus::PENDING);

    $data = new SaleData(
        exchangeRate: 1,
        taxes: [Tax::where('is_active', true)->value('id')],
        saleDate: now()->format('Y-m-d H:i:s'),
        carId: $sale->car_id,
        userId: $sale->user_id,
        branchId: $sale->branch_id,
        paymentCurrencyId: $sale->payment_currency_id,
        paymentMethodId: $sale->payment_method_id,
        discountPercentage: 0,
    );

    expect(fn () => app(SaleService::class)->createSale($data))
        ->toThrow(CarNotAvailableException::class, 'The car is not available for sale');

    expect($sale->car->fresh()->status)->toBe(CarStatus::RESERVED)
        ->and(Sale::find($sale->id)->status)->toBe(SaleStatus::PENDING);
});

it('renderiza la excepción de coche no disponible como 422 json', function () {
    $request = Request::create('/api/sales', 'POST', server: ['HTTP_ACCEPT' => 'application/json']);

    $response = app(ExceptionHandler::class)->render($request, CarNotAvailableException::sold());

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toMatchArray([
            'message' => 'The car cannot be sold because it is already marked as sold',
        ]);
});
