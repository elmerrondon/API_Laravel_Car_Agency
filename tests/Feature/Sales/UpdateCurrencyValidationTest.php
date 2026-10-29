<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Models\Sales\Currency;

beforeEach(fn () => actingAsRole(RoleEnum::MANAGER));

it('rechaza un code de moneda duplicado', function () {
    Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'is_base' => false, 'is_active' => true]);
    $euro = Currency::create(['name' => 'Euro', 'code' => 'EUR', 'is_base' => false, 'is_active' => true]);

    $this->patch("/api/currencies/{$euro->id}", [
        'code' => 'USD',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

it('permite un code que coincide con el nombre de otra moneda', function () {
    Currency::create(['name' => 'USD', 'code' => 'AAA', 'is_base' => false, 'is_active' => true]);
    $euro = Currency::create(['name' => 'Euro', 'code' => 'EUR', 'is_base' => false, 'is_active' => true]);

    $this->patch("/api/currencies/{$euro->id}", [
        'code' => 'USD',
    ])->assertOk()->assertJsonPath('data.code', 'USD');
});
