<?php

declare(strict_types=1);

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Tax;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());
});

/**
 * Payload de alta de coche; los tests sobrescriben solo lo necesario.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function carPayload(string $vin, array $overrides = []): array
{
    $source = Car::firstOrFail();

    return array_merge([
        'price' => 25000,
        'mileage' => 120,
        'year' => 2025,
        'vin' => $vin,
        'status' => CarStatus::AVAILABLE->value,
        'color_id' => $source->color_id,
        'car_model_id' => $source->car_model_id,
        'car_type_id' => $source->car_type_id,
        'category_id' => $source->category_id,
        'branch_id' => $source->branch_id,
    ], $overrides);
}

it('solo permite dar de alta coches disponibles', function (string $status) {
    $this->postJson('/api/cars', carPayload('1HGCM82633A004352', ['status' => $status]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect(Car::where('vin', '1HGCM82633A004352')->exists())->toBeFalse();
})->with([
    'reservado' => CarStatus::RESERVED->value,
    'vendido' => CarStatus::SOLD->value,
]);

it('permite la transición manual entre disponible y reservado', function () {
    $car = $this->postJson('/api/cars', carPayload('1HGCM82633A004353'))->assertCreated()->json('data');

    $this->patchJson("/api/cars/{$car['id']}", ['status' => CarStatus::RESERVED->value])
        ->assertOk()
        ->assertJsonPath('data.status', CarStatus::RESERVED->value);

    $this->patchJson("/api/cars/{$car['id']}", ['status' => CarStatus::AVAILABLE->value])
        ->assertOk()
        ->assertJsonPath('data.status', CarStatus::AVAILABLE->value);
});

it('rechaza marcar un coche como vendido desde la edición manual', function () {
    $car = $this->postJson('/api/cars', carPayload('1HGCM82633A004354'))->assertCreated()->json('data');

    $this->patchJson("/api/cars/{$car['id']}", ['status' => CarStatus::SOLD->value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    expect(Car::findOrFail($car['id'])->status)->toBe(CarStatus::AVAILABLE);
});

it('bloquea cambios manuales sobre un coche con venta completada y los libera al cancelar', function () {
    $car = $this->postJson('/api/cars', carPayload('1HGCM82633A004355'))->assertCreated()->json('data');

    $sale = $this->postJson('/api/sales', [
        'discount_percentage' => 0,
        'exchange_rate' => 1,
        'taxes' => [Tax::where('code', 'I0001')->value('id')],
        'sale_date' => '2026-02-10 10:30:00',
        'car_id' => $car['id'],
        'user_id' => User::where('email', 'admin@caragency.test')->value('id'),
        'branch_id' => $car['branch_id'],
        'payment_currency_id' => Currency::where('code', 'EUR')->value('id'),
        'payment_method_id' => PaymentMethod::firstOrFail()->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/cars/{$car['id']}", ['mileage' => 999])
        ->assertUnprocessable()
        ->assertJsonPath('message', sprintf(
            'The car %d has a completed sale and cannot be modified',
            $car['id'],
        ));

    expect(Car::findOrFail($car['id'])->mileage)->toBe(120)
        ->and(Car::findOrFail($car['id'])->status)->toBe(CarStatus::SOLD);

    $this->patchJson("/api/sales/{$sale['id']}/cancel", ['notes' => 'Devolución del cliente'])
        ->assertOk();

    $this->patchJson("/api/cars/{$car['id']}", ['mileage' => 999])
        ->assertOk()
        ->assertJsonPath('data.mileage', 999);
});
