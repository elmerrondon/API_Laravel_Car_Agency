<?php

use App\Enums\Users\RoleEnum;

beforeEach(function () {
    actingAsRole(RoleEnum::ADMIN);
});

it('devuelve JSON en un 404 de API sin cabecera Accept', function () {
    $response = $this->get('/api/ruta-inexistente');

    $response->assertNotFound();

    expect($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->json('message'))->toBeString()->not->toBeEmpty();
});

it('devuelve JSON en un 404 de recurso inexistente sin cabecera Accept', function () {
    $response = $this->get('/api/cars/999999');

    $response->assertNotFound();

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('devuelve JSON de validación en un 422 sin cabecera Accept', function () {
    $response = $this->post('/api/cars', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors([
            'price',
            'mileage',
            'year',
            'vin',
            'status',
            'color_id',
            'car_model_id',
            'car_type_id',
            'category_id',
            'branch_id',
        ]);

    expect($response->headers->get('content-type'))->toContain('application/json')
        ->and($response->json('message'))->toBeString()->not->toBeEmpty();
});

it('no fuerza JSON en las rutas web', function () {
    $response = $this->get('/');

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/html');
});
