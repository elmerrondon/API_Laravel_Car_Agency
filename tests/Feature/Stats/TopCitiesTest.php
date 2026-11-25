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
    $this->getJson('/api/stats/top-cities')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/top-cities')->assertForbidden();
});

it('agrupa las ventas por ciudad con estado y país', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()
        ->with('city.state.country')
        ->orderBy('id')
        ->get()
        ->unique('city_id')
        ->take(2)
        ->values();

    $cars = Car::query()->orderBy('id')->take(3)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[0], '2026-01-11 10:00:00', 200);
    createSaleRecord($cars[2], $this->admin, $branches[1], '2026-01-12 10:00:00', 50);

    $data = collect($this->getJson('/api/stats/top-cities')->assertOk()->json('data'));

    expect($data)->toHaveCount(2)
        ->and($data[0]['id'])->toBe($branches[0]->city->id)
        ->and($data[0]['name'])->toBe($branches[0]->city->name)
        ->and($data[0]['state'])->toBe($branches[0]->city->state->name)
        ->and($data[0]['country'])->toBe($branches[0]->city->state->country->name)
        ->and($data[0]['sales_count'])->toBe(2)
        ->and($data[0]['total_base_amount'])->toBe(300)
        ->and($data[1]['id'])->toBe($branches[1]->city->id)
        ->and($data[1]['sales_count'])->toBe(1);
});

it('aplica el límite dinámico y los filtros', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()
        ->with('city')
        ->orderBy('id')
        ->get()
        ->unique('city_id')
        ->take(2)
        ->values();

    $cars = Car::query()->orderBy('id')->take(2)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-01-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[1], '2026-02-10 10:00:00', 200);

    expect($this->getJson('/api/stats/top-cities?limit=1')->assertOk()->json('data'))->toHaveCount(1);

    $february = $this->getJson('/api/stats/top-cities?start_date=2026-02-01&end_date=2026-02-28')
        ->assertOk()
        ->json('data');

    expect($february)->toHaveCount(1)
        ->and($february[0]['id'])->toBe($branches[1]->city->id);

    $filtered = $this->getJson('/api/stats/top-cities?branch_id='.$branches[0]->id)
        ->assertOk()
        ->json('data');

    expect($filtered)->toHaveCount(1)
        ->and($filtered[0]['id'])->toBe($branches[0]->city->id);

    $this->getJson('/api/stats/top-cities?limit=101')->assertUnprocessable();
});
