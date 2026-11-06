<?php

declare(strict_types=1);

it('documenta el listado de auditoría, sus filtros y el permiso', function () {
    $index = openApiDocument()['paths']['/audit-logs']['get'];

    $parameters = collect($index['parameters'] ?? [])
        ->where('in', 'query')
        ->keyBy('name');

    expect($index['summary'])->toBe('Listar registros de auditoría')
        ->and($index['responses'])->toHaveKeys(['200', '401', '403', '422', '429'])
        ->and($parameters->keys()->all())->toContain(
            'user_id',
            'action',
            'auditable_type',
            'auditable_id',
            'from',
            'to',
            'per_page',
            'page',
        );

    foreach (['user_id', 'action', 'auditable_type', 'auditable_id', 'from', 'to', 'per_page'] as $name) {
        expect($parameters[$name]['description'])->not->toBeEmpty();
    }

    $show = openApiDocument()['paths']['/audit-logs/{audit_log}']['get'];

    expect($show['summary'])->toBe('Ver el registro de auditoría')
        ->and($show['responses'])->toHaveKeys(['200', '401', '403', '404', '429']);
});

it('documenta la exportación CSV de auditoría como binario', function () {
    $export = openApiDocument()['paths']['/audit-logs/export']['get'];

    expect($export['summary'])->toBe('Exportar registros de auditoría en CSV')
        ->and($export['responses'])->toHaveKeys(['200', '401', '403', '422', '429'])
        ->and($export['responses']['200']['content'])->toHaveKey('text/csv')
        ->and(array_keys($export['responses']['200']['content']))->toBe(['text/csv'])
        ->and($export['responses']['200']['content']['text/csv']['schema'])->toBe([
            'type' => 'string',
            'format' => 'binary',
        ]);
});

it('documenta las estadísticas, sus filtros y el límite', function () {
    $document = openApiDocument();

    $rankings = [
        '/stats/top-users',
        '/stats/top-branches',
        '/stats/top-cities',
        '/stats/top-brands',
        '/stats/top-models',
    ];

    foreach ($rankings as $path) {
        $operation = $document['paths'][$path]['get'];

        $parameters = collect($operation['parameters'] ?? [])
            ->where('in', 'query')
            ->keyBy('name');

        expect($operation['summary'])->not->toBeEmpty("{$path} sin resumen")
            ->and($operation['responses'])->toHaveKeys(['200', '401', '403', '422', '429'])
            ->and($parameters->keys()->all())->toContain('limit', 'start_date', 'end_date', 'branch_id')
            ->and($parameters['limit']['schema']['minimum'])->toBe(1)
            ->and($parameters['limit']['schema']['maximum'])->toBe(100)
            ->and($parameters['limit']['description'])->not->toBeEmpty()
            ->and($parameters['start_date']['description'])->not->toBeEmpty()
            ->and($parameters['end_date']['description'])->not->toBeEmpty();
    }

    $summary = collect($document['paths']['/stats/summary']['get']['parameters'] ?? [])
        ->where('in', 'query')
        ->keyBy('name');

    expect($document['paths']['/stats/summary']['get']['summary'])->toBe('Resumen de KPIs del negocio')
        ->and($summary->keys()->all())->toContain('start_date', 'end_date', 'branch_id')
        ->and($summary->has('limit'))->toBeFalse();

    $trend = collect($document['paths']['/stats/sales/trend']['get']['parameters'] ?? [])
        ->where('in', 'query')
        ->keyBy('name');

    expect($document['paths']['/stats/sales/trend']['get']['summary'])->toBe('Tendencia mensual de ventas')
        ->and($trend->keys()->all())->toContain('start_date', 'end_date', 'branch_id')
        ->and($trend->has('limit'))->toBeFalse();

    $inventory = collect($document['paths']['/stats/inventory']['get']['parameters'] ?? [])
        ->where('in', 'query')
        ->keyBy('name');

    expect($inventory->keys()->all())->toBe(['branch_id'])
        ->and($inventory['branch_id']['description'])->not->toBeEmpty();
});
