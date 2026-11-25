<?php

declare(strict_types=1);

use App\Exceptions\Config\InsecureTrustedProxyConfigurationException;
use App\Support\TrustedProxyPolicy;

it('admite proxies explícitos y vacíos fuera de producción', function () {
    expect(TrustedProxyPolicy::entries(' 203.0.113.10 , 198.51.100.0/24 '))
        ->toBe(['203.0.113.10', '198.51.100.0/24'])
        ->and(TrustedProxyPolicy::entries(''))->toBe([]);

    TrustedProxyPolicy::assertConfiguration('local', '*');
    TrustedProxyPolicy::assertConfiguration('testing', '*');
    TrustedProxyPolicy::assertConfiguration('local', '');
});

it('rechaza el comodín en producción, incluso mezclado con redes', function () {
    expect(fn () => TrustedProxyPolicy::assertConfiguration('production', '*'))
        ->toThrow(InsecureTrustedProxyConfigurationException::class);

    expect(fn () => TrustedProxyPolicy::assertConfiguration('production', '172.28.0.0/16, *'))
        ->toThrow(InsecureTrustedProxyConfigurationException::class);
});

it('acepta una IP o un rango explícito en producción', function () {
    expect(fn () => TrustedProxyPolicy::assertConfiguration('production', '172.28.0.0/16'))
        ->not->toThrow(InsecureTrustedProxyConfigurationException::class);

    expect(fn () => TrustedProxyPolicy::assertConfiguration('production', '203.0.113.10, 198.51.100.0/24'))
        ->not->toThrow(InsecureTrustedProxyConfigurationException::class);

    // Vacío desactiva la confianza en cabeceras: es seguro, no necesita `*` para
    // suplir la red del proxy.
    TrustedProxyPolicy::assertConfiguration('production', '');
});
