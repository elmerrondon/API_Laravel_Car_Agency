<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

it('expone todos los endpoints de la API', function () {
    $expected = [
        '/auth/login',
        '/auth/logout',
        '/auth/me',
        '/auth/password',
        '/auth/refresh',
        '/audit-logs',
        '/audit-logs/export',
        '/audit-logs/{audit_log}',
        '/branches',
        '/branches/{branch}',
        '/brands',
        '/brands/{brand}',
        '/car-models',
        '/car-models/{carModel}',
        '/car-statuses',
        '/car-types',
        '/car-types/{carType}',
        '/cars',
        '/cars/{car}',
        '/categories',
        '/categories/{category}',
        '/cities',
        '/cities/{city}',
        '/colors',
        '/colors/{color}',
        '/countries',
        '/countries/{country}',
        '/currencies',
        '/currencies/{currency}',
        '/payment-methods',
        '/payment-methods/{paymentMethod}',
        '/permissions',
        '/reports/cars',
        '/reports/cars/pdf',
        '/reports/sales/pdf',
        '/roles',
        '/roles/{role}',
        '/sales',
        '/sales/{sale}',
        '/sales/{sale}/cancel',
        '/states',
        '/states/{state}',
        '/stats/inventory',
        '/stats/sales/trend',
        '/stats/summary',
        '/stats/top-brands',
        '/stats/top-branches',
        '/stats/top-cities',
        '/stats/top-models',
        '/stats/top-users',
        '/taxes',
        '/taxes/{tax}',
        '/users',
        '/users/{user}',
        '/users/{user}/reset-password',
    ];

    $paths = array_keys(openApiDocument()['paths']);

    sort($expected);
    sort($paths);

    expect($paths)->toBe($expected);
});

it('usa los grupos de negocio en español con resumen en cada operación', function () {
    $document = openApiDocument();

    expect(array_column($document['tags'], 'name'))->toBe([
        'Autenticación',
        'Coches',
        'Catálogos',
        'Ubicaciones',
        'Ventas',
        'Reportes',
        'Usuarios',
        'Auditoría',
        'Estadísticas',
    ]);

    foreach ($document['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            expect($operation['tags'])->toHaveCount(1)
                ->and($operation['summary'])->not->toBeEmpty("{$method} {$path} sin resumen");
        }
    }
});

it('declara la licencia del proyecto y la seguridad bearer', function () {
    $document = openApiDocument();

    expect($document['info']['license'])->toMatchArray(['name' => 'MIT', 'identifier' => 'MIT'])
        ->and($document['components']['securitySchemes']['bearerAuth'])->toMatchArray([
            'type' => 'http',
            'scheme' => 'bearer',
        ]);

    // Login: público (sin 401 ni 403) y con límite de intentos.
    expect($document['paths']['/auth/login']['post']['responses'])
        ->toHaveKeys(['200', '422', '429']);

    foreach (['/auth/logout', '/auth/refresh'] as $path) {
        expect($document['paths'][$path]['post']['responses'])
            ->toHaveKeys(['200', '401', '429'])
            ->not->toHaveKey('403');
    }

    // Rutas con `permission:`: 401, 403 y el contrato de validación/búsqueda.
    $cars = $document['paths']['/cars'];

    expect($cars['get']['responses'])->toHaveKeys(['200', '401', '403', '422', '429'])
        ->and($cars['post']['responses'])->toHaveKeys(['200', '401', '403', '422', '429'])
        ->and($cars['post']['responses']['403']['content']['application/json']['schema'])
        ->toMatchArray(['type' => 'object', 'required' => ['message']]);

    expect($document['paths']['/cars/{car}']['get']['responses'])->toHaveKey('404');
});

it('no deja referencias internas rotas', function () {
    $document = openApiDocument();
    $json = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    preg_match_all('/"#\/components\/([a-zA-Z]+)\/([^"]+)"/', $json, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();

    foreach ($matches as [, $section, $name]) {
        expect(Arr::get($document, "components.{$section}.{$name}"))->not->toBeNull(
            "Referencia rota: #/components/{$section}/{$name}"
        );
    }
});
