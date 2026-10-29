<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('crea los índices de consulta previstos', function () {
    expect(Schema::hasIndex('cars', ['status']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['status', 'sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('sales', ['branch_id', 'sale_date']))->toBeTrue()
        ->and(Schema::hasIndex('users', ['is_active']))->toBeTrue();
});

it('conserva la unicidad global de vin sin índices duplicados', function () {
    expect(Schema::hasIndex('cars', 'cars_vin_unique'))->toBeTrue()
        ->and(Schema::hasIndex('cars', 'deleted_at'))->toBeFalse();
});
