<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Cars\Color;
use App\Models\Locations\Branch;
use App\Models\Locations\City;
use App\Models\Locations\State;
use App\Models\Users\Permission;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::where('email', 'admin@caragency.test')->firstOrFail());

    $this->sellerRoleId = Role::where('name', RoleEnum::SELLER->value)->value('id');
    $this->branchId = Branch::firstOrFail()->id;
});

it('permite el login con el email en mayúsculas', function () {
    Cache::flush();

    // `Sanctum::actingAs` del beforeEach deja el guard por defecto como
    // RequestGuard, que no implementa `attempt`.
    $this->app['auth']->shouldUse('web');
    $this->app['auth']->forgetGuards();

    $this->postJson('/api/auth/login', [
        'email' => Str::upper('admin@caragency.test'),
        'password' => 'password',
    ])->assertOk()->assertJsonStructure(['token']);
});

it('rechaza un catálogo con el mismo nombre en otra capitalización', function () {
    $this->postJson('/api/colors', ['name' => 'NEGRO'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->postJson('/api/car-types', [
        'name' => 'sedán',
        'description' => 'Tipo duplicado',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('permite actualizar un catálogo conservando su propio nombre', function () {
    $color = Color::where('name', 'Negro')->firstOrFail();

    $this->patchJson("/api/colors/{$color->id}", ['name' => 'NEGRO'])
        ->assertOk()
        ->assertJsonPath('data.name', 'NEGRO');
});

it('rechaza un usuario con email o documento en otra capitalización', function () {
    $payload = [
        'name' => 'Carla',
        'last_name' => 'Ruiz',
        'document_number' => 'DOC40004',
        'code' => 'USR-901',
        'email' => 'carla@example.test',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'roles' => [$this->sellerRoleId],
        'branches' => [$this->branchId],
    ];

    $this->postJson('/api/users', array_merge($payload, [
        'email' => Str::upper('admin@caragency.test'),
    ]))->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/users', array_merge($payload, [
        'document_number' => 'v-10000001',
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_number');

    expect(User::where('email', 'carla@example.test')->exists())->toBeFalse();
});

it('rechaza un rol con el mismo nombre en otra capitalización', function () {
    $permissionId = Permission::query()->value('id');

    $this->postJson('/api/roles', [
        'name' => 'Auditor',
        'description' => 'Rol de auditoría',
        'permissions' => [$permissionId],
    ])->assertCreated();

    $this->postJson('/api/roles', [
        'name' => 'AUDITOR',
        'description' => 'Rol duplicado',
        'permissions' => [$permissionId],
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rechaza una ciudad duplicada en el mismo estado y la acepta en otro', function () {
    $city = City::with('state')->firstOrFail();
    $otherState = State::whereKeyNot($city->state_id)->firstOrFail();

    $this->postJson('/api/cities', [
        'name' => Str::upper($city->name),
        'state_id' => $city->state_id,
    ])->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->postJson('/api/cities', [
        'name' => $city->name,
        'state_id' => $otherState->id,
    ])->assertCreated();
});

it('normaliza el vin a mayúsculas al crear y actualizar un coche', function () {
    $source = Car::firstOrFail();

    $created = $this->postJson('/api/cars', [
        'price' => '25000.50',
        'mileage' => 1200,
        'year' => 2025,
        'vin' => '1hgbh41jxmn10918c',
        'status' => 'available',
        'color_id' => $source->color_id,
        'car_model_id' => $source->car_model_id,
        'car_type_id' => $source->car_type_id,
        'category_id' => $source->category_id,
        'branch_id' => $source->branch_id,
    ])->assertCreated()
        ->assertJsonPath('data.vin', '1HGBH41JXMN10918C')
        ->json('data');

    $this->patchJson("/api/cars/{$created['id']}", ['vin' => '1hgbh41jxmn10918d'])
        ->assertOk()
        ->assertJsonPath('data.vin', '1HGBH41JXMN10918D');
});

it('normaliza los códigos de catálogo y la identidad del usuario', function () {
    $this->postJson('/api/currencies', ['name' => 'Libra Esterlina', 'code' => 'gbp'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'GBP');

    $this->postJson('/api/taxes', ['name' => 'Prueba Fiscal', 'code' => 'i009999', 'percentage' => 5])
        ->assertCreated()
        ->assertJsonPath('data.code', 'I009999');

    $created = $this->postJson('/api/users', [
        'name' => 'Carla',
        'last_name' => 'Ruiz',
        'document_number' => 'doc40004',
        'code' => 'usr-901',
        'email' => 'Carla.Mayus@Example.TEST',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'roles' => [$this->sellerRoleId],
        'branches' => [$this->branchId],
    ])->assertCreated()
        ->assertJsonPath('data.document_number', 'DOC40004')
        ->assertJsonPath('data.code', 'USR-901')
        ->assertJsonPath('data.email', 'carla.mayus@example.test')
        ->json('data');

    expect(User::findOrFail($created['id'])->email)->toBe('carla.mayus@example.test');
});
