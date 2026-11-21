<?php

declare(strict_types=1);

use App\DTOs\Sales\CurrencyData;
use App\Enums\Users\RoleEnum;
use App\Exceptions\Sales\BaseCurrencyAlreadyExistsException;
use App\Models\Sales\Currency;
use App\Services\Sales\CurrencyService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => actingAsRole(RoleEnum::MANAGER));

it('rechaza una segunda moneda base a nivel de base de datos', function () {
    Currency::create(['name' => 'Dólar', 'code' => 'USD', 'is_base' => true, 'is_active' => true]);

    expect(fn () => Currency::create(['name' => 'Euro', 'code' => 'EUR', 'is_base' => true, 'is_active' => true]))
        ->toThrow(QueryException::class);
});

it('traduce la violación del indice de moneda base a una excepcion de dominio', function () {
    // Concurrencia controlada: otra petición crea la moneda base justo después
    // de que el servicio compruebe que no existe. Solo la BD puede detectarla.
    $injected = false;
    DB::listen(function ($query) use (&$injected): void {
        if (! $injected && str_contains($query->sql, 'from "currencies"')) {
            $injected = true;
            Currency::create(['name' => 'Dólar', 'code' => 'USD', 'is_base' => true, 'is_active' => true]);
        }
    });

    expect(fn () => app(CurrencyService::class)->createCurrency(new CurrencyData(
        name: 'Euro',
        code: 'EUR',
        isBase: true,
        isActive: true,
    )))->toThrow(BaseCurrencyAlreadyExistsException::class, 'already exists');

    expect($injected)->toBeTrue();
});

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
