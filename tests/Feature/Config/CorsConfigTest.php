<?php

declare(strict_types=1);

use App\Exceptions\Config\InsecureCorsConfigurationException;
use App\Support\CorsPolicy;

it('mantiene el comodín de CORS en local y testing', function () {
    expect(config('cors.allowed_origins'))->toBe(['*']);

    CorsPolicy::assertAllowedOrigins('local', ['*']);
    CorsPolicy::assertAllowedOrigins('testing', ['*']);
    CorsPolicy::assertAllowedOrigins('local', []);
});

it('rechaza el comodín y la lista vacía en producción', function () {
    expect(fn () => CorsPolicy::assertAllowedOrigins('production', ['*']))
        ->toThrow(InsecureCorsConfigurationException::class);

    expect(fn () => CorsPolicy::assertAllowedOrigins('production', []))
        ->toThrow(InsecureCorsConfigurationException::class);

    expect(fn () => CorsPolicy::assertAllowedOrigins('production', ['https://a.example.com', '*']))
        ->toThrow(InsecureCorsConfigurationException::class);
});

it('acepta orígenes explícitos en producción', function () {
    expect(fn () => CorsPolicy::assertAllowedOrigins('production', ['https://app.example.com']))
        ->not->toThrow(InsecureCorsConfigurationException::class);
});
