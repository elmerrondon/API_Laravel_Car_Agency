<?php

declare(strict_types=1);

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
    $this->getJson('/api/stats/top-brands')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/top-brands')->assertForbidden();
});

it('agrupa por marca y cuenta coches con soft delete', function () {
    Sanctum::actingAs($this->admin);

    $branch = Branch::firstOrFail();

    $carA = Car::query()->with('carModel')->orderBy('id')->firstOrFail();
    $brandA = $carA->carModel->brand_id;

    $carB = Car::query()
        ->with('carModel')
        ->whereHas('carModel', fn ($query) => $query->where('brand_id', '<>', $brandA))
        ->orderBy('id')
        ->firstOrFail();
    $brandB = $carB->carModel->brand_id;

    createSaleRecord($carA, $this->admin, $branch, '2026-01-10 10:00:00', 100);
    createSaleRecord($carA, $this->admin, $branch, '2026-01-11 10:00:00', 200);
    createSaleRecord($carB, $this->admin, $branch, '2026-01-12 10:00:00', 50);

    // El coche sale del inventario, pero su histórico sigue contando.
    $carA->delete();

    $data = collect($this->getJson('/api/stats/top-brands')->assertOk()->json('data'));

    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($brandA)
        ->and($data[0]['sales_count'])->toBe(2)
        ->and($data[0]['total_base_amount'])->toBe(300)
        ->and($data[1]['id'])->toBe($brandB)
        ->and($data[1]['sales_count'])->toBe(1)
        ->and($data[1]['total_base_amount'])->toBe(50);
});

it('aplica el límite dinámico y los filtros', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()->orderBy('id')->take(2)->get();

    $carA = Car::query()->with('carModel')->orderBy('id')->firstOrFail();
    $brandA = $carA->carModel->brand_id;
    $carB = Car::query()
        ->with('carModel')
        ->whereHas('carModel', fn ($query) => $query->where('brand_id', '<>', $brandA))
        ->orderBy('id')
        ->firstOrFail();

    createSaleRecord($carA, $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($carB, $this->admin, $branches[1], '2026-02-10 10:00:00', 200);

    expect($this->getJson('/api/stats/top-brands?limit=1')->assertOk()->json('data'))->toHaveCount(1);

    $february = $this->getJson('/api/stats/top-brands?start_date=2026-02-01&end_date=2026-02-28')
        ->assertOk()
        ->json('data');

    expect($february)->toHaveCount(1)
        ->and($february[0]['id'])->toBe($carB->carModel->brand_id);

    $filtered = $this->getJson('/api/stats/top-brands?branch_id='.$branches[0]->id)
        ->assertOk()
        ->json('data');

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['id'])->toBe($brandA);

    $this->getJson('/api/stats/top-brands?limit=101')->assertUnprocessable();
});
