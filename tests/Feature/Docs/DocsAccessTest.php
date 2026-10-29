<?php

declare(strict_types=1);

use function Pest\Laravel\get;

it('sirve la documentación openapi en entornos local y testing', function () {
    get('/docs/api')->assertOk();

    get('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.2')
        ->assertJsonPath('components.securitySchemes.bearerAuth.scheme', 'bearer')
        ->assertJsonPath('servers.0.url', fn (string $url) => str_ends_with($url, '/api'));
});

it('bloquea la documentación fuera de local/testing cuando DOCS_ENABLED está apagado', function () {
    $this->app['env'] = 'production';
    config(['docs.enabled' => false]);

    get('/docs/api')->assertForbidden();
    get('/docs/api.json')->assertForbidden();
});

it('abre la documentación en producción cuando DOCS_ENABLED está encendido', function () {
    $this->app['env'] = 'production';
    config(['docs.enabled' => true]);

    get('/docs/api')->assertOk();
    get('/docs/api.json')->assertOk();
});
