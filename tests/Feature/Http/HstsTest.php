<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| HSTS
|--------------------------------------------------------------------------
|
| La cabecera solo puede viajar sobre HTTPS. El caso del proxy es el que usa
| producción: la petición llega a PHP por HTTP y `TrustProxies` la marca como
| segura al leer `X-Forwarded-Proto`.
|
*/

beforeEach(function () {
    config([
        'security.hsts.enabled' => true,
        'security.hsts.max_age' => 31536000,
        'security.hsts.include_subdomains' => false,
    ]);
});

it('añade HSTS cuando la petición llega por HTTPS', function () {
    $this->getJson('https://localhost/api/cars')
        ->assertUnauthorized()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('añade HSTS cuando un proxy de confianza reenvía HTTPS', function () {
    config(['trustedproxy.proxies' => '203.0.113.10']);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeader('X-Forwarded-Proto', 'https')
        ->getJson('/api/cars')
        ->assertUnauthorized()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('incluye los subdominios cuando la configuración lo pide', function () {
    config(['security.hsts.include_subdomains' => true]);

    $this->getJson('https://localhost/api/cars')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('no añade HSTS sobre HTTP plano', function () {
    $this->getJson('/api/cars')
        ->assertUnauthorized()
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('no añade HSTS cuando la cabecera está desactivada', function () {
    config(['security.hsts.enabled' => false]);

    $this->getJson('https://localhost/api/cars')
        ->assertHeaderMissing('Strict-Transport-Security');
});
