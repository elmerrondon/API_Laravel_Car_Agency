<?php

declare(strict_types=1);

use App\Enums\Cars\CarStatus;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
});

it('exige autenticación y el permiso view_statistics', function () {
    $this->getJson('/api/stats/inventory')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/inventory')->assertForbidden();
});

it('resume el inventario actual por estado y excluye soft deletes', function () {
    Sanctum::actingAs($this->admin);

    $cars = Car::query()->orderBy('id')->take(3)->get();

    $cars[0]->update(['status' => CarStatus::RESERVED]);
    $cars[1]->update(['status' => CarStatus::SOLD]);
    $cars[2]->delete();

    $response = $this->getJson('/api/stats/inventory')->assertOk();
    $byStatus = collect($response->json('by_status'))->keyBy('status');

    expect($response->json('total_cars'))->toBe(51)
        ->and($response->json('total_value'))->toBe(round((float) Car::query()->sum('price'), 2))
        ->and($byStatus['available']['count'])->toBe(49)
        ->and($byStatus['available']['label'])->toBe('Disponible')
        ->and($byStatus['reserved']['count'])->toBe(1)
        ->and($byStatus['reserved']['label'])->toBe('Reservado')
        ->and($byStatus['sold']['count'])->toBe(1)
        ->and($byStatus['sold']['label'])->toBe('Vendido')
        ->and($byStatus->sum('count'))->toBe(51);
});

it('desglosa el inventario por sucursal', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->getJson('/api/stats/inventory')->assertOk();
    $byBranch = collect($response->json('by_branch'));

    expect($byBranch->count())->toBeGreaterThan(0)
        ->and($byBranch->count())->toBeLessThanOrEqual(8)
        ->and($byBranch->sum('count'))->toBe(52);

    foreach ($byBranch as $branch) {
        $expected = Car::query()->where('branch_id', $branch['id'])->count();

        expect($branch['count'])->toBe($expected);
    }
});

it('filtra el inventario por sucursal', function () {
    Sanctum::actingAs($this->admin);

    $branch = Branch::firstOrFail();
    $expectedCars = Car::query()->where('branch_id', $branch->id)->count();
    $expectedValue = round((float) Car::query()->where('branch_id', $branch->id)->sum('price'), 2);

    $response = $this->getJson('/api/stats/inventory?branch_id='.$branch->id)->assertOk();

    expect($response->json('meta.branch_id'))->toBe($branch->id)
        ->and($response->json('total_cars'))->toBe($expectedCars)
        ->and($response->json('total_value'))->toBe($expectedValue)
        ->and($response->json('by_branch'))->toHaveCount(1)
        ->and($response->json('by_branch.0.id'))->toBe($branch->id);
});

it('valida la sucursal', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/stats/inventory?branch_id=999999')->assertUnprocessable();
});
