<?php

declare(strict_types=1);

use App\Enums\Users\PermissionEnum;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\Tax;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionAdminSeeder;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
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
    'audit_logs',
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
        ->and(Branch::count())->toBe(8)
        ->and(Currency::count())->toBeGreaterThan(0)
        ->and(Tax::count())->toBeGreaterThan(0)
        ->and(Car::count())->toBe(52);

    expect(Role::where('name', RoleEnum::ADMIN->value)->exists())->toBeTrue()
        ->and(Permission::where('name', PermissionEnum::VIEW_CARS->value)->exists())->toBeTrue()
        ->and(Branch::where('name', 'Sede Ventura Plaza')->exists())->toBeTrue();
});

it('siembra los permisos de forma idempotente', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(PermissionSeeder::class);

    expect(Permission::count())->toBe(count(PermissionEnum::cases()))
        ->and(Permission::where('name', PermissionEnum::VIEW_REPORTS->value)->value('description'))
        ->toBe(PermissionEnum::VIEW_REPORTS->description());
});

it('siembra los usuarios base con rol y sucursal', function () {
    $this->seed(DatabaseSeeder::class);

    $password = env('SEED_USER_PASSWORD', 'password');

    foreach ([
        'admin@caragency.test' => RoleEnum::ADMIN,
        'manager@caragency.test' => RoleEnum::MANAGER,
        'vendedor@caragency.test' => RoleEnum::SELLER,
    ] as $email => $role) {
        $user = User::where('email', $email)->first();

        expect($user)->not->toBeNull()
            ->and($user->roles()->pluck('name')->all())->toBe([$role->value])
            ->and($user->branches()->count())->toBe(1)
            ->and($user->must_change_password)->toBeFalse()
            ->and(Hash::check($password, $user->password))->toBeTrue();
    }
});

it('asigna la matriz de permisos esperada a cada rol', function () {
    $this->seed(DatabaseSeeder::class);

    $permissionCount = fn (RoleEnum $role) => Role::where('name', $role->value)
        ->firstOrFail()
        ->permissions()
        ->count();

    expect($permissionCount(RoleEnum::ADMIN))->toBe(14)
        ->and($permissionCount(RoleEnum::MANAGER))->toBe(10)
        ->and($permissionCount(RoleEnum::SELLER))->toBe(5);

    expect(RolePermissionSeeder::matrix()[RoleEnum::ADMIN->value])->toHaveCount(14)
        ->and(RolePermissionSeeder::matrix()[RoleEnum::MANAGER->value])->toHaveCount(10)
        ->and(RolePermissionSeeder::matrix()[RoleEnum::SELLER->value])->toHaveCount(5);
});

it('siembra el administrador de producción sin Faker ni datos demo', function () {
    config([
        'seeders.admin_name' => 'Root',
        'seeders.admin_last_name' => 'Producción',
        'seeders.admin_document_number' => 'V-90000001',
        'seeders.admin_code' => 'USR-9001',
        'seeders.admin_email' => 'root@agency.test',
        'seeders.admin_password' => 'S3gura-2026',
    ]);

    $this->seed(ProductionAdminSeeder::class);

    $user = User::where('email', 'root@agency.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->code)->toBe('USR-9001')
        ->and($user->is_active)->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->roles()->pluck('name')->all())->toBe([RoleEnum::ADMIN->value])
        ->and($user->roles()->first()->permissions()->count())->toBe(14)
        ->and(Hash::check('S3gura-2026', $user->password))->toBeTrue()
        ->and(User::count())->toBe(1)
        ->and(Car::count())->toBe(0)
        ->and(Branch::count())->toBe(0);
});

it('es idempotente al sembrar dos veces el administrador de producción', function () {
    config([
        'seeders.admin_code' => 'USR-9002',
        'seeders.admin_email' => 'idempotente@agency.test',
        'seeders.admin_password' => 'S3gura-2026',
    ]);

    $this->seed(ProductionAdminSeeder::class);
    $this->seed(ProductionAdminSeeder::class);

    $user = User::where('code', 'USR-9002')->firstOrFail();

    expect(User::count())->toBe(1)
        ->and($user->roles()->count())->toBe(1)
        ->and(Hash::check('S3gura-2026', $user->password))->toBeTrue();
});

it('en producción DatabaseSeeder solo ejecuta el seeder fundacional', function () {
    app()['env'] = 'production';

    config([
        'seeders.admin_code' => 'USR-9003',
        'seeders.admin_email' => 'prod@agency.test',
        'seeders.admin_document_number' => 'V-90000003',
        'seeders.admin_password' => 'S3gura-2026',
    ]);

    $this->artisan('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ])->assertSuccessful();

    expect(User::count())->toBe(1)
        ->and(User::where('email', 'prod@agency.test')->exists())->toBeTrue()
        ->and(Role::count())->toBe(count(RoleEnum::cases()))
        ->and(Permission::count())->toBe(count(PermissionEnum::cases()))
        ->and(Car::count())->toBe(0)
        ->and(Branch::count())->toBe(0)
        ->and(Currency::count())->toBe(0);
});
