<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Escenario de ranking: admin (3 ventas, sucursal A), manager (1 venta,
 * sucursal A) y un usuario histórico con soft delete (2 ventas, sucursal B).
 */
function seedTopUsersScenario(): void
{
    $admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $manager = User::where('email', 'manager@caragency.test')->firstOrFail();
    $branches = Branch::query()->orderBy('id')->take(2)->get();
    $cars = Car::query()->orderBy('id')->take(6)->get();

    createSaleRecord($cars[0], $admin, $branches[0], '2026-01-05 10:00:00', 100);
    createSaleRecord($cars[1], $admin, $branches[0], '2026-01-06 10:00:00', 200);
    createSaleRecord($cars[2], $admin, $branches[0], '2026-01-07 10:00:00', 300);

    createSaleRecord($cars[3], $manager, $branches[0], '2026-03-05 10:00:00', 1000);
    createSaleRecord($cars[3], $manager, $branches[0], '2026-03-06 10:00:00', 999, SaleStatus::CANCELLED);

    $ghost = User::factory()->create([
        'name' => 'Usuario',
        'last_name' => 'Histórico',
        'code' => 'USR-999',
    ]);
    $ghost->delete();

    createSaleRecord($cars[4], $ghost, $branches[1], '2026-02-06 10:00:00', 50);
    createSaleRecord($cars[5], $ghost, $branches[1], '2026-02-07 10:00:00', 50);
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
});

it('exige autenticación y el permiso view_statistics', function () {
    $this->getJson('/api/stats/top-users')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/top-users')->assertForbidden();
});

it('ordena por ventas, facturación y muestra usuarios históricos', function () {
    Sanctum::actingAs($this->admin);
    seedTopUsersScenario();

    $data = collect($this->getJson('/api/stats/top-users')->assertOk()->json('data'));

    expect($data)->toHaveCount(3)
        ->and($data[0]['code'])->toBe('USR-0001')
        ->and($data[0]['sales_count'])->toBe(3)
        ->and($data[0]['total_base_amount'])->toBe(600)
        ->and($data[1]['code'])->toBe('USR-999')
        ->and($data[1]['sales_count'])->toBe(2)
        ->and($data[1]['total_base_amount'])->toBe(100)
        ->and($data[2]['sales_count'])->toBe(1)
        ->and($data[2]['total_base_amount'])->toBe(1000);
});

it('usa limit 10 por defecto y devuelve menos filas si no alcanzan', function () {
    Sanctum::actingAs($this->admin);
    seedTopUsersScenario();

    $response = $this->getJson('/api/stats/top-users')->assertOk();

    expect($response->json('meta.limit'))->toBe(10)
        ->and($response->json('data'))->toHaveCount(3);

    expect($this->getJson('/api/stats/top-users?limit=5')->assertOk()->json('data'))->toHaveCount(3);
});

it('respeta el límite dinámico', function () {
    Sanctum::actingAs($this->admin);
    seedTopUsersScenario();

    expect($this->getJson('/api/stats/top-users?limit=1')->assertOk()->json('data'))->toHaveCount(1);

    $two = $this->getJson('/api/stats/top-users?limit=2')->assertOk();

    expect($two->json('data'))->toHaveCount(2)
        ->and($two->json('meta.limit'))->toBe(2);
});

it('rechaza límites fuera del rango 1-100', function (string $query) {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/stats/top-users?'.$query)->assertUnprocessable();
})->with([
    'cero' => 'limit=0',
    'negativo' => 'limit=-1',
    'excesivo' => 'limit=101',
    'texto' => 'limit=abc',
]);

it('filtra por rango de fechas y sucursal', function () {
    Sanctum::actingAs($this->admin);
    seedTopUsersScenario();

    $branches = Branch::query()->orderBy('id')->take(2)->get();

    $february = $this->getJson('/api/stats/top-users?start_date=2026-02-01&end_date=2026-02-28')
        ->assertOk()
        ->json('data');

    expect($february)->toHaveCount(1)
        ->and($february[0]['code'])->toBe('USR-999');

    $branchA = $this->getJson('/api/stats/top-users?branch_id='.$branches[0]->id)
        ->assertOk()
        ->json('data');

    expect($branchA)->toHaveCount(2)
        ->and($branchA[0]['sales_count'])->toBe(3);

    $branchB = $this->getJson('/api/stats/top-users?branch_id='.$branches[1]->id)
        ->assertOk()
        ->json('data');

    expect($branchB)->toHaveCount(1)
        ->and($branchB[0]['code'])->toBe('USR-999');
});
