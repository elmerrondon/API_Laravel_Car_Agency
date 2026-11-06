<?php

declare(strict_types=1);

it('documenta el autoservicio de identidad y el reset administrativo', function () {
    $document = openApiDocument();

    expect($document['paths']['/auth/me']['get']['summary'])
        ->toBe('Consultar el perfil del usuario autenticado')
        ->and($document['paths']['/auth/me']['get']['responses'])->toHaveKeys(['200', '401', '429']);

    expect($document['paths']['/auth/password']['put']['summary'])
        ->toBe('Cambiar la contraseña propia')
        ->and($document['paths']['/auth/password']['put']['responses'])->toHaveKeys(['200', '401', '422', '429']);

    $reset = $document['paths']['/users/{user}/reset-password']['post'];

    expect($reset['summary'])->toBe('Restablecer la contraseña de un usuario')
        ->and($reset['responses'])->toHaveKeys(['200', '401', '403', '422', '429']);
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
