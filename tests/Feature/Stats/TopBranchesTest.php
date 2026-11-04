<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
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
    $this->getJson('/api/stats/top-branches')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/top-branches')->assertForbidden();
});

it('ordena las sucursales por ventas y facturación', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()->with('city')->orderBy('id')->take(3)->get();
    $cars = Car::query()->orderBy('id')->take(5)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[0], '2026-01-11 10:00:00', 200);

    createSaleRecord($cars[2], $this->admin, $branches[1], '2026-01-12 10:00:00', 10);
    createSaleRecord($cars[3], $this->admin, $branches[1], '2026-01-13 10:00:00', 20);
    createSaleRecord($cars[4], $this->admin, $branches[1], '2026-01-14 10:00:00', 30);

    createSaleRecord($cars[0], $this->admin, $branches[2], '2026-01-15 10:00:00', 999, SaleStatus::CANCELLED);

    $data = collect($this->getJson('/api/stats/top-branches')->assertOk()->json('data'));

    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($branches[1]->id)
        ->and($data[0]['code'])->toBe($branches[1]->code)
        ->and($data[0]['city'])->toBe($branches[1]->city->name)
        ->and($data[0]['sales_count'])->toBe(3)
        ->and($data[0]['total_base_amount'])->toBe(60)
        ->and($data[1]['id'])->toBe($branches[0]->id)
        ->and($data[1]['sales_count'])->toBe(2)
        ->and($data[1]['total_base_amount'])->toBe(300);
});

it('aplica el límite dinámico y no inventa sucursales sin ventas', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()->orderBy('id')->take(2)->get();
    $cars = Car::query()->orderBy('id')->take(2)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[1], '2026-01-10 10:00:00', 200);

    $limited = $this->getJson('/api/stats/top-branches?limit=1')->assertOk();

    expect($limited->json('data'))->toHaveCount(1)
        ->and($limited->json('meta.limit'))->toBe(1);

    expect($this->getJson('/api/stats/top-branches?limit=10')->assertOk()->json('data'))->toHaveCount(2);

    $this->getJson('/api/stats/top-branches?limit=101')->assertUnprocessable();
});

it('filtra por rango de fechas y sucursal', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()->orderBy('id')->take(2)->get();
    $cars = Car::query()->orderBy('id')->take(2)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[1], '2026-02-10 10:00:00', 200);

    $february = $this->getJson('/api/stats/top-branches?start_date=2026-02-01&end_date=2026-02-28')
        ->assertOk()
        ->json('data');

    expect($february)->toHaveCount(1)
        ->and($february[0]['id'])->toBe($branches[1]->id);

    $filtered = $this->getJson('/api/stats/top-branches?branch_id='.$branches[0]->id)
        ->assertOk()
        ->json('data');

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['id'])->toBe($branches[0]->id);
});
