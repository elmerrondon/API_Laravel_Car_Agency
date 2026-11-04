<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('exige autenticación y el permiso view_statistics', function () {
    $this->getJson('/api/stats/sales/trend')->assertUnauthorized();
});

it('rechaza a un vendedor sin view_statistics', function () {
    actingAsRole(RoleEnum::SELLER);

    $this->getJson('/api/stats/sales/trend')->assertForbidden();
});

it('usa los últimos doce meses cuando no hay fechas', function () {
    Sanctum::actingAs($this->admin);
    Carbon::setTestNow('2026-11-15 12:00:00');

    $response = $this->getJson('/api/stats/sales/trend')->assertOk();
    $data = collect($response->json('data'));

    expect($data)->toHaveCount(12)
        ->and($data->first()['period'])->toBe('2025-12')
        ->and($data->last()['period'])->toBe('2026-11')
        ->and($data->every(fn (array $row): bool => $row['sales_count'] === 0 && $row['total_base_amount'] === 0))
        ->toBeTrue()
        ->and($response->json('meta.start_date'))->toBe('2025-12-01')
        ->and($response->json('meta.end_date'))->toBe('2026-11-01');
});

it('agrupa por mes y rellena los huecos con cero', function () {
    Sanctum::actingAs($this->admin);

    $cars = Car::query()->orderBy('id')->take(4)->get();
    $branch = Branch::firstOrFail();

    createSaleRecord($cars[0], $this->admin, $branch, '2026-01-31 23:59:59', 100);
    createSaleRecord($cars[1], $this->admin, $branch, '2026-01-01 00:00:00', 50);
    createSaleRecord($cars[2], $this->admin, $branch, '2026-03-15 12:00:00', 200.25);
    createSaleRecord($cars[3], $this->admin, $branch, '2026-03-16 12:00:00', 300, SaleStatus::CANCELLED);

    $data = collect(
        $this->getJson('/api/stats/sales/trend?start_date=2026-01-01&end_date=2026-04-30')
            ->assertOk()
            ->json('data'),
    );

    expect($data)->toHaveCount(4)
        ->and($data->pluck('period')->all())->toBe(['2026-01', '2026-02', '2026-03', '2026-04'])
        ->and($data[0]['sales_count'])->toBe(2)
        ->and($data[0]['total_base_amount'])->toBe(150)
        ->and($data[1]['sales_count'])->toBe(0)
        ->and($data[1]['total_base_amount'])->toBe(0)
        ->and($data[2]['sales_count'])->toBe(1)
        ->and($data[2]['total_base_amount'])->toBe(200.25)
        ->and($data[3]['sales_count'])->toBe(0)
        ->and($data[3]['total_base_amount'])->toBe(0);
});

it('filtra la tendencia por sucursal', function () {
    Sanctum::actingAs($this->admin);

    $branches = Branch::query()->orderBy('id')->take(2)->get();
    $cars = Car::query()->orderBy('id')->take(2)->get();

    createSaleRecord($cars[0], $this->admin, $branches[0], '2026-02-10 10:00:00', 100);
    createSaleRecord($cars[1], $this->admin, $branches[1], '2026-02-11 10:00:00', 200);

    $data = collect(
        $this->getJson('/api/stats/sales/trend?start_date=2026-02-01&end_date=2026-02-28&branch_id='.$branches[1]->id)
            ->assertOk()
            ->json('data'),
    );

    expect($data)->toHaveCount(1)
        ->and($data[0]['period'])->toBe('2026-02')
        ->and($data[0]['sales_count'])->toBe(1)
        ->and($data[0]['total_base_amount'])->toBe(200);
});

it('valida las fechas', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/stats/sales/trend?start_date=2026-01-01')->assertUnprocessable();
    $this->getJson('/api/stats/sales/trend?end_date=2026-01-31')->assertUnprocessable();
    $this->getJson('/api/stats/sales/trend?start_date=2026-02-01&end_date=2026-01-01')->assertUnprocessable();
});
