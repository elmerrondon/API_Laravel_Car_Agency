<?php

declare(strict_types=1);

use App\Enums\Sales\SaleStatus;
use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\Sale;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Siembra ventas completadas con fechas conocidas sobre los primeros coches.
 */
function seedSales(int $count): void
{
    $user = User::firstOrCreate(
        ['email' => 'ana@example.test'],
        [
            'name' => 'Ana',
            'last_name' => 'Pérez',
            'document_number' => 'DOC10001',
            'code' => 'USR-001',
            'password' => 'password',
            'is_active' => true,
        ],
    );

    $cars = Car::query()->take($count)->get();
    $branch = Branch::firstOrFail();
    $baseCurrency = Currency::where('is_base', true)->firstOrFail();
    $paymentCurrency = Currency::where('code', 'EUR')->firstOrFail();
    $paymentMethod = PaymentMethod::firstOrFail();

    foreach ($cars as $index => $car) {
        Sale::create([
            'exchange_rate' => 1,
            'base_price' => $car->price,
            'taxes' => [],
            'total_taxes' => 0,
            'discount_percentage' => 0,
            'total_base_amount' => $car->price,
            'total_amount_paid' => $car->price,
            'total_discount' => 0,
            'status' => SaleStatus::COMPLETED,
            'sale_date' => now()->subMinutes($index),
            'car_id' => $car->id,
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'payment_currency_id' => $paymentCurrency->id,
            'base_currency_id' => $baseCurrency->id,
            'payment_method_id' => $paymentMethod->id,
        ]);
    }
}

/**
 * Siembra roles y permisos y autentica un usuario nuevo con el rol indicado.
 */
function actingAsRole(RoleEnum $role): User
{
    test()->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $user = User::factory()->create();
    $user->roles()->sync([Role::where('name', $role->value)->value('id')]);

    Sanctum::actingAs($user);

    return $user;
}
