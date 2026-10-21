<?php

use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

/**
 * Crea una venta completada para un coche disponible y lo marca como vendido.
 */
function createCompletedSale(): Sale
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

    $car->update(['status' => CarStatus::SOLD]);

    return Sale::create([
        'exchange_rate' => 1,
        'base_price' => $car->price,
        'taxes' => [],
        'total_taxes' => 0,
        'discount_percentage' => 0,
        'total_base_amount' => $car->price,
        'total_amount_paid' => $car->price,
        'total_discount' => 0,
        'status' => SaleStatus::COMPLETED,
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
    $sale = createCompletedSale();

    $response = $this->patchJson("/api/sales/{$sale->id}/cancel", [
        'notes' => 'El cliente desistió de la compra',
    ]);

    $response->assertOk();

    expect($sale->fresh()->status)->toBe(SaleStatus::CANCELLED)
        ->and($sale->fresh()->notes)->toBe('El cliente desistió de la compra')
        ->and($sale->car->fresh()->status)->toBe(CarStatus::AVAILABLE);
});
