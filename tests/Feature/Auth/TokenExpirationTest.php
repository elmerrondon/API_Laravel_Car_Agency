<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed([
        RoleSeeder::class,
        PermissionSeeder::class,
        RolePermissionSeeder::class,
    ]);

    $this->user = User::factory()->create();
    $this->user->roles()->sync([
        Role::where('name', RoleEnum::ADMIN->value)->value('id'),
    ]);

    $this->token = $this->user->createToken('api-token')->plainTextToken;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('acepta el token dentro de la ventana de expiración', function () {
    expect(config('sanctum.expiration'))->toBe(120);

    Carbon::setTestNow(now()->addMinutes(119));
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertOk();
});

it('rechaza el token pasada la ventana de expiración', function () {
    Carbon::setTestNow(now()->addMinutes(121));
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
});

it('respeta la expiración configurable de sanctum', function () {
    config(['sanctum.expiration' => 30]);

    Carbon::setTestNow(now()->addMinutes(29));
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertOk();

    Carbon::setTestNow(now()->addMinutes(2));
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
});

it('aplica la expiración configurada cuando el guard se vuelve a resolver', function () {
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertOk();

    config(['sanctum.expiration' => 1]);
    Carbon::setTestNow(now()->addMinutes(5));
    $this->app['auth']->forgetGuards();

    $this->withToken($this->token)->getJson('/api/cars')->assertUnauthorized();
});
