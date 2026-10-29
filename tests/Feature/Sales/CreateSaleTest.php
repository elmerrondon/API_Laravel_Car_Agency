<?php

declare(strict_types=1);

use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());
});

/**
 * Toma un coche disponible y le fija un precio conocido para el cálculo.
 */
function sellingCar(float $price): Car
{
    $car = Car::where('status', CarStatus::AVAILABLE)->firstOrFail();
    $car->update(['price' => $price]);

    return $car;
}

/**
 * Payload válido de venta; los tests sobrescriben solo lo que necesitan.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function salePayload(Car $car, array $overrides = []): array
{
    return array_merge([
        'discount_percentage' => 0,
        'exchange_rate' => 1,
        'taxes' => [Tax::where('code', 'I0001')->value('id')],
        'sale_date' => '2026-02-10 10:30:00',
        'car_id' => $car->id,
        'user_id' => User::where('email', 'manager@caragency.test')->value('id'),
        'branch_id' => Branch::firstOrFail()->id,
        'payment_currency_id' => Currency::where('code', 'EUR')->value('id'),
        'payment_method_id' => PaymentMethod::firstOrFail()->id,
    ], $overrides);
}

it('calcula impuestos, descuento y tipo de cambio sobre el precio del coche', function () {
    $car = sellingCar(10000.10);

    $iva = Tax::where('code', 'I0001')->firstOrFail();
    $extra = Tax::where('code', 'I0002')->firstOrFail();
    $iva->update(['percentage' => 16]);
    $extra->update(['percentage' => 3]);

    $response = $this->postJson('/api/sales', salePayload($car, [
        'discount_percentage' => 7.5,
        'exchange_rate' => 1.15,
        'taxes' => [$iva->id, $extra->id],
    ]))->assertCreated();

    $data = $response->json('data');

    expect(round((float) $data['base_price'], 2))->toBe(10000.10)
        ->and(round((float) $data['discount_percentage'], 2))->toBe(7.5)
        ->and(round((float) $data['total_discount'], 2))->toBe(750.01)
        ->and(round((float) $data['total_taxes'], 2))->toBe(1757.51)
        ->and(round((float) $data['total_base_amount'], 2))->toBe(11007.60)
        ->and(round((float) $data['total_amount_paid'], 2))->toBe(12658.74)
        ->and(round((float) $data['exchange_rate'], 2))->toBe(1.15)
        ->and($data['status'])->toBe(SaleStatus::COMPLETED->value)
        ->and($data['car']['status'])->toBe(CarStatus::SOLD->value)
        ->and($data['base_currency']['code'])->toBe('USD')
        ->and($data['payment_currency']['code'])->toBe('EUR');

    expect($data['taxes'])->toHaveCount(2)
        ->and($data['taxes'][0])->toMatchArray([
            'id' => $iva->id,
            'name' => $iva->name,
            'percentage' => 16.0,
            'amount_applied' => 1480.01,
        ])
        ->and($data['taxes'][1])->toMatchArray([
            'id' => $extra->id,
            'percentage' => 3.0,
            'amount_applied' => 277.50,
        ]);

    expect($car->fresh()->status)->toBe(CarStatus::SOLD);
});

it('guarda los importes derivados redondeados a dos decimales', function () {
    $car = sellingCar(19.99);

    $tax = Tax::where('code', 'I0001')->firstOrFail();
    $tax->update(['percentage' => 7.77]);

    $data = $this->postJson('/api/sales', salePayload($car, [
        'discount_percentage' => 12.5,
        'exchange_rate' => 1.11,
        'taxes' => [$tax->id],
    ]))->assertCreated()->json('data');

    $sale = Sale::findOrFail($data['id']);

    expect(round((float) $sale->total_discount, 2))->toBe(2.50)
        ->and(round((float) $sale->total_taxes, 2))->toBe(1.36)
        ->and(round((float) $sale->total_base_amount, 2))->toBe(18.85)
        ->and(round((float) $sale->total_amount_paid, 2))->toBe(20.92)
        ->and(round((float) $sale->taxes[0]['amount_applied'], 2))->toBe(1.36);

    foreach ([
        $sale->total_discount,
        $sale->total_taxes,
        $sale->total_base_amount,
        $sale->total_amount_paid,
    ] as $amount) {
        expect((float) $amount)->toEqual(round((float) $amount, 2));
    }
});

it('rechaza con 422 un coche que no está disponible', function () {
    $car = Car::where('status', CarStatus::AVAILABLE)->firstOrFail();
    $car->update(['status' => CarStatus::RESERVED]);

    $this->postJson('/api/sales', salePayload($car))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('car_id');

    expect(Sale::count())->toBe(0)
        ->and($car->fresh()->status)->toBe(CarStatus::RESERVED);
});

it('rechaza una fecha de venta que no sea Y-m-d H:i:s', function () {
    $car = sellingCar(1000);

    foreach (['2026-02-10', '10/02/2026 10:30:00', '2026-02-10T10:30:00'] as $invalid) {
        $this->postJson('/api/sales', salePayload($car, ['sale_date' => $invalid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sale_date');
    }

    expect(Sale::count())->toBe(0)
        ->and($car->fresh()->status)->toBe(CarStatus::AVAILABLE);
});

it('valida impuestos, descuento y tipo de cambio', function () {
    $car = sellingCar(1000);

    $inactive = Tax::where('code', 'I0003')->firstOrFail();
    $inactive->update(['is_active' => false]);

    $active = Tax::where('code', 'I0001')->value('id');

    $this->postJson('/api/sales', salePayload($car, ['taxes' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taxes');

    $this->postJson('/api/sales', salePayload($car, ['taxes' => [$inactive->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taxes.0');

    $this->postJson('/api/sales', salePayload($car, ['taxes' => [$active, $active]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('taxes.1');

    $this->postJson('/api/sales', salePayload($car, ['discount_percentage' => 100.01]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('discount_percentage');

    $this->postJson('/api/sales', salePayload($car, ['exchange_rate' => 1.234]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('exchange_rate');

    expect(Sale::count())->toBe(0);
});
