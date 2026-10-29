<?php

declare(strict_types=1);

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
use App\Models\Users\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->admin = User::where('email', 'admin@caragency.test')->firstOrFail();
});

it('devuelve 404 json para una ruta de api inexistente', function () {
    Sanctum::actingAs($this->admin);

    $this->getJson('/api/ruta-inexistente')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('devuelve 404 json para un recurso borrado lógicamente', function () {
    Sanctum::actingAs($this->admin);

    $car = Car::firstOrFail();
    $id = $car->id;

    $this->deleteJson("/api/cars/{$id}")->assertNoContent();

    $this->getJson("/api/cars/{$id}")
        ->assertNotFound()
        ->assertJsonStructure(['message']);

    expect(Car::find($id))->toBeNull()
        ->and(Car::withTrashed()->find($id))->not->toBeNull()
        ->and(Car::withTrashed()->find($id)->status)->toBe(CarStatus::AVAILABLE);
});

it('devuelve 405 json cuando el método no está permitido', function () {
    $this->deleteJson('/api/auth/login')
        ->assertStatus(405)
        ->assertJsonStructure(['message']);

    Sanctum::actingAs($this->admin);

    $this->putJson('/api/auth/logout')
        ->assertStatus(405)
        ->assertJsonStructure(['message']);
});

it('responde en json aunque el cliente no envíe la cabecera Accept', function () {
    $response = $this->put('/api/auth/login', []);

    $response->assertStatus(405);

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('limita el login en json con Retry-After y se libera al pasar el minuto', function () {
    Cache::flush();

    $payload = ['email' => 'bloqueado@example.test', 'password' => 'wrong-password'];

    for ($attempt = 0; $attempt < config('rate_limits.login'); $attempt++) {
        $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
    }

    $response = $this->postJson('/api/auth/login', $payload)
        ->assertStatus(429)
        ->assertJsonStructure(['message']);

    expect($response->headers->has('Retry-After'))->toBeTrue()
        ->and($response->headers->get('content-type'))->toContain('application/json');

    $this->travel(61)->seconds();

    $this->postJson('/api/auth/login', $payload)->assertUnauthorized();

    $this->postJson('/api/auth/login', [
        'email' => $this->admin->email,
        'password' => 'password',
    ])->assertOk();
});

it('devuelve 401 json en los grupos protegidos sin token', function () {
    foreach (['/api/users', '/api/cars', '/api/reports/cars', '/api/sales'] as $route) {
        $response = $this->get($route);

        $response->assertUnauthorized();

        expect($response->headers->get('content-type'))->toContain('application/json');
    }
});

it('responde 500 con un mensaje claro si la ruta exige un permiso inexistente', function () {
    Sanctum::actingAs($this->admin);

    Route::middleware(['auth:sanctum', 'permission:permiso_inexistente'])
        ->get('/api/_test/permiso-inexistente', fn (): array => ['ok' => true]);

    $this->getJson('/api/_test/permiso-inexistente')
        ->assertStatus(500)
        ->assertJsonPath('message', 'Unknown permission [permiso_inexistente].');
});

it('devuelve 403 json con mensaje cuando falta el permiso', function () {
    Sanctum::actingAs(User::where('email', 'vendedor@caragency.test')->firstOrFail());

    $this->getJson('/api/users')
        ->assertForbidden()
        ->assertJsonPath('message', 'This action is unauthorized.');
});
