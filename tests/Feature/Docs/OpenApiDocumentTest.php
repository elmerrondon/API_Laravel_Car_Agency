<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

use function Pest\Laravel\get;

/**
 * Genera el documento una sola vez por ejecución del archivo: el contrato no
 * depende de la base de datos y Scramble tarda ~1 s en construirlo.
 *
 * @return array<string, mixed>
 */
function openApiDocument(): array
{
    /** @var array<string, mixed>|null $document */
    static $document = null;

    return $document ??= get('/docs/api.json')->json();
}

it('expone todos los endpoints de la API', function () {
    $expected = [
        '/auth/login',
        '/auth/logout',
        '/auth/refresh',
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
        '/taxes',
        '/taxes/{tax}',
        '/users',
        '/users/{user}',
    ];

    $paths = array_keys(openApiDocument()['paths']);

    sort($expected);
    sort($paths);

    expect($paths)->toBe($expected);
});

it('usa los siete grupos de negocio en español con resumen en cada operación', function () {
    $document = openApiDocument();

    expect(array_column($document['tags'], 'name'))->toBe([
        'Autenticación',
        'Coches',
        'Catálogos',
        'Ubicaciones',
        'Ventas',
        'Reportes',
        'Usuarios',
    ]);

    foreach ($document['paths'] as $path => $operations) {
        foreach ($operations as $method => $operation) {
            expect($operation['tags'])->toHaveCount(1)
                ->and($operation['summary'])->not->toBeEmpty("{$method} {$path} sin resumen");
        }
    }
});

it('documenta la seguridad bearer y las respuestas de error de la API', function () {
    $document = openApiDocument();

    expect($document['components']['securitySchemes']['bearerAuth'])->toMatchArray([
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

it('documenta el 409 de cancelar una venta y de los borrados con claves foráneas', function () {
    $document = openApiDocument();

    expect($document['paths']['/sales/{sale}/cancel']['patch']['responses'])->toHaveKey('409')
        // Eliminar una marca referenciada por un coche choca con la FK.
        ->and($document['paths']['/brands/{brand}']['delete']['responses'])->toHaveKey('409');

    // Los recursos con borrado lógico no pueden devolver 409 al eliminarse.
    expect($document['paths']['/cars/{car}']['delete']['responses'])->not->toHaveKey('409')
        ->and($document['paths']['/users/{user}']['delete']['responses'])->not->toHaveKey('409');
});

it('documenta los PDF como binario y no como JSON', function (string $path) {
    $response = openApiDocument()['paths'][$path]['get']['responses']['200'];

    expect($response['content'])->toHaveKey('application/pdf')
        ->and($response['content'])->not->toHaveKey('application/json')
        ->and($response['content']['application/pdf']['schema'])->toBe([
            'type' => 'string',
            'format' => 'binary',
        ]);
})->with(['/reports/cars/pdf', '/reports/sales/pdf']);

it('documenta page y per_page en los listados paginados', function () {
    foreach (openApiDocument()['paths'] as $path => $operations) {
        if (! isset($operations['get'])) {
            continue;
        }

        $parameters = collect($operations['get']['parameters'] ?? [])
            ->where('in', 'query')
            ->keyBy('name');

        if (! $parameters->has('per_page')) {
            continue;
        }

        expect($parameters->has('page'))->toBeTrue("{$path} no documenta `page`")
            ->and($parameters['page']['schema']['minimum'])->toBe(1)
            ->and($parameters['per_page']['schema']['maximum'])->toBe(100)
            ->and($parameters['page']['description'])->not->toBeEmpty()
            ->and($parameters['per_page']['description'])->not->toBeEmpty();
    }
});

it('documenta los filtros de los reportes', function () {
    $parameters = collect(openApiDocument()['paths']['/reports/sales/pdf']['get']['parameters'])
        ->where('in', 'query')
        ->keyBy('name');

    expect($parameters->keys()->all())->toBe(['from', 'to', 'branch_id'])
        ->and($parameters['from']['schema']['format'])->toBe('date')
        ->and($parameters['branch_id']['schema']['minimum'])->toBe(1);

    foreach (['from', 'to', 'branch_id'] as $name) {
        expect($parameters[$name]['description'])->not->toBeEmpty();
    }
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
