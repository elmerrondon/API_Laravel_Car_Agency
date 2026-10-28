<?php

use App\Enums\Users\RoleEnum;
use App\Models\Sales\Currency;

beforeEach(fn () => actingAsRole(RoleEnum::MANAGER));

it('no permite desmarcar la moneda base', function () {
    $currency = Currency::create([
        'name' => 'Dólar',
        'code' => 'USD',
        'is_base' => true,
        'is_active' => true,
    ]);

    $this->patchJson("/api/currencies/{$currency->id}", [
        'is_base' => false,
    ])
        ->assertStatus(409)
        ->assertJson([
            'message' => 'The base currency cannot be unset because the system needs a base currency',
        ]);
});
