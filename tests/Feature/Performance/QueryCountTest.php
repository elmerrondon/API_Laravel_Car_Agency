<?php

use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
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

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
});

it('mantiene constante el número de consultas del listado de coches', function () {
    $smallPage = countQueriesDuringRequest(function () {
        $this->getJson('/api/cars?per_page=5')->assertOk();
    });

    $largePage = countQueriesDuringRequest(function () {
        $this->getJson('/api/cars?per_page=50')->assertOk();
    });

    expect($largePage)->toBe($smallPage);
});

it('mantiene constante el número de consultas del listado de ventas', function () {
    $user = User::create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'document_number' => 'DOC10001',
        'code' => 'USR-001',
        'email' => 'ana@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $cars = Car::query()->take(20)->get();
    $branch = Branch::firstOrFail();
    $baseCurrency = Currency::where('is_base', true)->firstOrFail();
    $paymentCurrency = Currency::where('code', 'EUR')->firstOrFail();
    $paymentMethod = PaymentMethod::firstOrFail();

    foreach ($cars as $index => $car) {
        Sale::create([
            'exchange_rate' => 1,
            'base_price' => $car->price,
            'taxes' => [],
            'total_taxes' => 0,
            'discount_percentage' => 0,
            'total_base_amount' => $car->price,
            'total_amount_paid' => $car->price,
            'total_discount' => 0,
            'status' => SaleStatus::COMPLETED,
            'sale_date' => now()->subMinutes($index),
            'car_id' => $car->id,
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'payment_currency_id' => $paymentCurrency->id,
            'base_currency_id' => $baseCurrency->id,
            'payment_method_id' => $paymentMethod->id,
        ]);
    }

    $smallPage = countQueriesDuringRequest(function () {
        $this->getJson('/api/sales?per_page=5')->assertOk();
    });

    $largePage = countQueriesDuringRequest(function () {
        $this->getJson('/api/sales?per_page=20')->assertOk();
    });

    expect($largePage)->toBe($smallPage);
});
