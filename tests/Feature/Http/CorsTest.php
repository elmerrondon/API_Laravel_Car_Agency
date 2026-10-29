<?php

declare(strict_types=1);

it('devuelve cabeceras cors en las respuestas del api', function () {
    $this->withHeaders(['Origin' => 'http://localhost:3000'])
        ->getJson('/api/cars')
        ->assertUnauthorized()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('responde al preflight de cors con 204', function () {
    $this->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Access-Control-Request-Method' => 'GET',
    ])->options('/api/cars')
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

it('expone la configuración cors por defecto', function () {
    expect(config('cors.allowed_origins'))->toBe(['*'])
        ->and(config('cors.paths'))->toContain('api/*');
});
