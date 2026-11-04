<?php

declare(strict_types=1);

use App\Enums\Audit\AuditAction;
use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Models\Audit\AuditLog;
use App\Models\Cars\Car;
use App\Models\Cars\CarModel;
use App\Models\Cars\CarType;
use App\Models\Cars\Category;
use App\Models\Cars\Color;
use App\Models\Locations\Branch;
use App\Models\Locations\City;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);
});

it('audita el ciclo de vida de un coche', function () {
    $created = $this->postJson('/api/cars', [
        'price' => 25000,
        'mileage' => 120,
        'year' => 2025,
        'vin' => '1HGCM82633A004352',
        'status' => CarStatus::AVAILABLE->value,
        'color_id' => Color::firstOrFail()->id,
        'car_model_id' => CarModel::firstOrFail()->id,
        'car_type_id' => CarType::firstOrFail()->id,
        'category_id' => Category::firstOrFail()->id,
        'branch_id' => Branch::firstOrFail()->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/cars/{$created['id']}", ['mileage' => 200])->assertOk();
    $this->deleteJson("/api/cars/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::CAR_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::CAR_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::CAR_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->auditable_type)->toBe('car')
        ->and($createdLog->new_values['vin'])->toBe('1HGCM82633A004352')
        ->and($createdLog->new_values['status'])->toBe(CarStatus::AVAILABLE->value)
        ->and($updatedLog->old_values['mileage'])->toBe(120)
        ->and($updatedLog->new_values['mileage'])->toBe(200)
        ->and($deletedLog->old_values['vin'])->toBe('1HGCM82633A004352');
});

it('audita el ciclo de vida de una sucursal', function () {
    $created = $this->postJson('/api/branches', [
        'name' => 'Sucursal Auditada',
        'address' => 'Calle Auditoría 123',
        'description' => 'Sucursal para probar la auditoría',
        'zip_code' => 'A12345',
        'city_id' => City::firstOrFail()->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/branches/{$created['id']}", ['description' => 'Actualizada'])->assertOk();
    $this->deleteJson("/api/branches/{$created['id']}")->assertNoContent();

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::BRANCH_CREATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $updatedLog = AuditLog::query()
        ->where('action', AuditAction::BRANCH_UPDATED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    $deletedLog = AuditLog::query()
        ->where('action', AuditAction::BRANCH_DELETED->value)
        ->where('auditable_id', $created['id'])
        ->firstOrFail();

    expect($createdLog->auditable_type)->toBe('branch')
        ->and($createdLog->new_values['code'])->toBe($created['code'])
        ->and($updatedLog->old_values['description'])->toBe('Sucursal para probar la auditoría')
        ->and($updatedLog->new_values['description'])->toBe('Actualizada')
        ->and($deletedLog->old_values['code'])->toBe($created['code']);
});

it('audita la creación y cancelación de una venta después del commit', function () {
    $car = Car::where('status', CarStatus::AVAILABLE)->firstOrFail();
    $tax = Tax::where('code', 'I0001')->firstOrFail();
    $branch = Branch::firstOrFail();
    $paymentCurrency = Currency::where('code', 'EUR')->firstOrFail();
    $paymentMethod = PaymentMethod::firstOrFail();

    $sale = $this->postJson('/api/sales', [
        'discount_percentage' => 0,
        'exchange_rate' => 1,
        'taxes' => [$tax->id],
        'sale_date' => '2026-02-10 10:30:00',
        'car_id' => $car->id,
        'user_id' => $this->admin->id,
        'branch_id' => $branch->id,
        'payment_currency_id' => $paymentCurrency->id,
        'payment_method_id' => $paymentMethod->id,
    ])->assertCreated()->json('data');

    $this->patchJson("/api/sales/{$sale['id']}/cancel", [
        'notes' => 'Cancelada para probar la auditoría',
    ])->assertOk();

    $saleModel = Sale::findOrFail($sale['id']);

    $createdLog = AuditLog::query()
        ->where('action', AuditAction::SALE_CREATED->value)
        ->where('auditable_id', $sale['id'])
        ->firstOrFail();

    $cancelledLog = AuditLog::query()
        ->where('action', AuditAction::SALE_CANCELLED->value)
        ->where('auditable_id', $sale['id'])
        ->firstOrFail();

    expect($createdLog->auditable_type)->toBe('sale')
        ->and($createdLog->new_values['status'])->toBe(SaleStatus::COMPLETED->value)
        ->and($createdLog->new_values['total_base_amount'])->toEqual((float) $saleModel->total_base_amount)
        ->and($createdLog->new_values['car_id'])->toBe($car->id)
        ->and($createdLog->new_values['branch_id'])->toBe($branch->id)
        ->and($createdLog->new_values['user_id'])->toBe($this->admin->id)
        ->and($cancelledLog->old_values['status'])->toBe(SaleStatus::COMPLETED->value)
        ->and($cancelledLog->new_values['status'])->toBe(SaleStatus::CANCELLED->value)
        ->and($cancelledLog->old_values['notes'])->toBeNull();
});
