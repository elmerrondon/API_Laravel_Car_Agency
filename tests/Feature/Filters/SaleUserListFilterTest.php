<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
    $this->manager = User::where('email', 'manager@caragency.test')->firstOrFail();

    Sanctum::actingAs($this->admin);

    $cars = Car::query()->orderBy('id')->take(2)->get();
    $branches = Branch::query()->orderBy('id')->take(2)->get();

    $this->completed = createSaleRecord(
        $cars[0], $this->admin, $branches[0], '2026-01-15 10:00:00', 1000, SaleStatus::COMPLETED,
    );
    $this->cancelled = createSaleRecord(
        $cars[1], $this->manager, $branches[1], '2026-02-20 10:00:00', 2000, SaleStatus::CANCELLED,
    );
});

it('filtra ventas por estado, sucursal, vendedor, método de pago y moneda', function () {
    $response = $this->getJson('/api/sales?'.http_build_query([
        'status' => SaleStatus::COMPLETED->value,
        'branch_id' => $this->completed->branch_id,
        'user_id' => $this->admin->id,
        'payment_method_id' => $this->completed->payment_method_id,
        'payment_currency_id' => $this->completed->payment_currency_id,
    ]))->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$this->completed->id]);
});

it('filtra ventas por rango de fechas inclusivo', function () {
    $response = $this->getJson('/api/sales?from=2026-02-01&to=2026-02-28')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$this->cancelled->id]);

    $all = $this->getJson('/api/sales?from=2026-01-01&to=2026-02-28')->assertOk();
    expect($all->json('meta.total'))->toBe(2);
});

it('exige el par de fechas completo', function () {
    $this->getJson('/api/sales?from=2026-01-01')->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('filtra usuarios por rol, sucursal, estado y búsqueda', function () {
    $inactive = User::factory()->create([
        'is_active' => false,
        'password' => Hash::make('secret123'),
    ]);
    $roleId = Role::where('name', RoleEnum::MANAGER->value)->value('id');

    $byRole = $this->getJson('/api/users?role_id='.$roleId)->assertOk();
    expect(array_column($byRole->json('data'), 'id'))->toBe([$this->manager->id]);

    $byBranch = $this->getJson('/api/users?branch_id='.$this->admin->branches()->value('branches.id'))->assertOk();
    expect($byBranch->json('data'))->not->toBeEmpty();

    $byActive = $this->getJson('/api/users?is_active=false')->assertOk();
    expect(array_column($byActive->json('data'), 'id'))->toContain($inactive->id);

    foreach ($byActive->json('data') as $user) {
        expect($user['is_active'])->toBeFalse();
    }

    $byName = $this->getJson('/api/users?search=man')->assertOk();
    expect(array_column($byName->json('data'), 'id'))->toBe([$this->manager->id]);

    $byCode = $this->getJson('/api/users?search=usr-0003')->assertOk();
    expect(array_column($byCode->json('data'), 'id'))
        ->toBe([User::where('code', 'USR-0003')->value('id')]);
});

it('filtra roles por prefijo del nombre', function () {
    $response = $this->getJson('/api/roles?search=adm')->assertOk();

    expect(array_column($response->json('data'), 'name'))->toBe([RoleEnum::ADMIN->value]);
});
