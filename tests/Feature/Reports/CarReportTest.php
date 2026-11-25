<?php

declare(strict_types=1);

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use App\Services\Reports\CarReportService;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('exige autenticación para el reporte de coches', function () {
    $this->getJson('/api/reports/cars')->assertUnauthorized();
});

it('rechaza el reporte de coches sin el permiso view_reports', function () {
    Sanctum::actingAs(User::where('email', 'vendedor@caragency.test')->firstOrFail());

    $this->getJson('/api/reports/cars')->assertForbidden();
});

it('devuelve el agregado de coches por tipo, marca y estado', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());

    $first = Car::firstOrFail();
    $first->update(['status' => CarStatus::SOLD]);
    Car::whereKeyNot($first->id)->firstOrFail()->update(['status' => CarStatus::RESERVED]);

    $response = $this->getJson('/api/reports/cars')->assertOk();

    $response->assertJsonStructure([
        'filters' => ['branch_id'],
        'branch',
        'total_cars',
        'total_price',
        'base_currency',
        'by_type' => [['name', 'count', 'total_price']],
        'by_brand' => [['name', 'count', 'total_price']],
        'by_status' => [['status', 'label', 'count', 'total_price']],
    ]);

    $total = Car::count();

    expect($response->json('total_cars'))->toBe($total)
        ->and(array_sum(array_column($response->json('by_type'), 'count')))->toBe($total)
        ->and(array_sum(array_column($response->json('by_brand'), 'count')))->toBe($total)
        ->and(array_sum(array_column($response->json('by_status'), 'count')))->toBe($total)
        ->and(array_column($response->json('by_status'), 'status'))->toBe(['available', 'reserved', 'sold'])
        ->and(array_column($response->json('by_status'), 'label'))->toBe(['Disponible', 'Reservado', 'Vendido'])
        ->and($response->json('base_currency'))->toBe('USD');
});

it('filtra el reporte de coches por sucursal', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());

    $branchId = Car::firstOrFail()->branch_id;
    $branch = Branch::findOrFail($branchId);

    $response = $this->getJson("/api/reports/cars?branch_id={$branchId}")->assertOk();

    expect($response->json('total_cars'))->toBe(Car::where('branch_id', $branchId)->count())
        ->and($response->json('branch'))->toBe($branch->name)
        ->and($response->json('filters.branch_id'))->toBe($branchId);
});

it('valida la sucursal del reporte de coches', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());

    $this->getJson('/api/reports/cars?branch_id=999999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('branch_id');
});

it('descarga el reporte de coches con cabecera PDF', function () {
    Sanctum::actingAs(User::where('email', 'manager@caragency.test')->firstOrFail());

    $response = $this->get('/api/reports/cars/pdf');

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('attachment')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('incluye los agregados en la vista del reporte de coches', function () {
    $report = app(CarReportService::class)->build();

    $html = view('reports.cars-pdf', $report)->render();

    expect($html)->toContain('Reporte de inventario')
        ->toContain('Por estado')
        ->toContain('Por tipo')
        ->toContain('Por marca')
        ->toContain('Disponible')
        ->toContain('USD');
});
