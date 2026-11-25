<?php

declare(strict_types=1);

use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
});

it('aplica las mismas reglas de límite en todos los rankings', function (string $endpoint) {
    Sanctum::actingAs($this->admin);
    seedSales(3);

    $this->getJson($endpoint.'?limit=20')
        ->assertOk()
        ->assertJsonPath('meta.limit', 20);

    $this->getJson($endpoint.'?limit=100')
        ->assertOk()
        ->assertJsonPath('meta.limit', 100);

    $this->getJson($endpoint.'?limit=101')->assertUnprocessable();
    $this->getJson($endpoint.'?limit=0')->assertUnprocessable();
    $this->getJson($endpoint.'?limit=-5')->assertUnprocessable();
})->with([
    '/api/stats/top-users',
    '/api/stats/top-branches',
    '/api/stats/top-cities',
    '/api/stats/top-brands',
    '/api/stats/top-models',
]);

it('devuelve menos filas cuando hay menos datos que el límite', function (string $endpoint) {
    Sanctum::actingAs($this->admin);
    seedSales(2);

    $data = $this->getJson($endpoint.'?limit=100')->assertOk()->json('data');

    expect(count($data))->toBeLessThanOrEqual(2);
})->with([
    '/api/stats/top-users',
    '/api/stats/top-branches',
    '/api/stats/top-cities',
    '/api/stats/top-brands',
    '/api/stats/top-models',
]);
