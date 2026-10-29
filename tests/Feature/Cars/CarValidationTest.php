<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use Database\Seeders\DatabaseSeeder;

function carValidationPayload(Car $source, string $vin, int $year = 2025): array
{
    return [
        'price' => '25000.50',
        'mileage' => 1200,
        'year' => $year,
        'vin' => $vin,
        'status' => 'available',
        'color_id' => $source->color_id,
        'car_model_id' => $source->car_model_id,
        'car_type_id' => $source->car_type_id,
        'category_id' => $source->category_id,
        'branch_id' => $source->branch_id,
    ];
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    actingAsRole(RoleEnum::ADMIN);
});

it('no permite reutilizar el VIN de un coche eliminado', function () {
    $source = Car::firstOrFail();
    $payload = carValidationPayload($source, '1HGBH41JXMN10918A');

    $created = $this->postJson('/api/cars', $payload)->assertCreated();
    $carId = $created->json('data.id');

    $this->deleteJson("/api/cars/{$carId}")->assertNoContent();

    $this->postJson('/api/cars', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vin');
});

it('rechaza un año fuera del rango permitido', function () {
    $source = Car::firstOrFail();
    $payload = carValidationPayload($source, '1HGBH41JXMN10918B', 70000);

    $this->postJson('/api/cars', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('year');
});
