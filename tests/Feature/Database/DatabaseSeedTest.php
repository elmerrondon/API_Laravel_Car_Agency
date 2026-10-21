<?php

use App\Enums\Users\PermissionEnum;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\Tax;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Illuminate\Support\Facades\Schema;

$expectedTables = [
    'users',
    'roles',
    'permissions',
    'role_user',
    'permission_role',
    'branch_user',
    'countries',
    'states',
    'cities',
    'branches',
    'car_types',
    'categories',
    'brands',
    'car_models',
    'colors',
    'cars',
    'currencies',
    'payment_methods',
    'taxes',
    'sales',
    'personal_access_tokens',
];

it('ejecuta las migraciones en una base de datos limpia', function () use ($expectedTables) {
    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Falta la tabla {$table}");
    }
});

it('siembra los datos base con DatabaseSeeder', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::count())->toBe(count(RoleEnum::cases()))
        ->and(Permission::count())->toBe(count(PermissionEnum::cases()))
        ->and(Branch::count())->toBeGreaterThan(0)
        ->and(Currency::count())->toBeGreaterThan(0)
        ->and(Tax::count())->toBeGreaterThan(0)
        ->and(Car::count())->toBeGreaterThanOrEqual(50);

    expect(Role::where('name', RoleEnum::ADMIN->value)->exists())->toBeTrue()
        ->and(Permission::where('name', PermissionEnum::VIEW_CARS->value)->exists())->toBeTrue();
});

it('siembra los permisos de forma idempotente', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toBe(count(PermissionEnum::cases()))
        ->and(Permission::where('name', PermissionEnum::VIEW_REPORTS->value)->value('description'))
        ->toBe(PermissionEnum::VIEW_REPORTS->description());
});

it('asigna la matriz de permisos esperada a cada rol', function () {
    $this->seed(DatabaseSeeder::class);

    $permissionCount = fn (RoleEnum $role) => Role::where('name', $role->value)
        ->firstOrFail()
        ->permissions()
        ->count();

    expect($permissionCount(RoleEnum::ADMIN))->toBe(13)
        ->and($permissionCount(RoleEnum::MANAGER))->toBe(10)
        ->and($permissionCount(RoleEnum::SELLER))->toBe(5);

    expect(RolePermissionSeeder::matrix()[RoleEnum::ADMIN->value])->toHaveCount(13)
        ->and(RolePermissionSeeder::matrix()[RoleEnum::MANAGER->value])->toHaveCount(10)
        ->and(RolePermissionSeeder::matrix()[RoleEnum::SELLER->value])->toHaveCount(5);
});
