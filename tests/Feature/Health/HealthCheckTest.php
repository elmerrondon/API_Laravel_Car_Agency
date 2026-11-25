<?php

declare(strict_types=1);

use App\Services\Health\HealthChecker;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;

it('responde 200 sin detalles cuando la base y la caché responden', function () {
    $this->get('/up')
        ->assertOk()
        ->assertExactJson(['status' => 'ok']);
});

it('responde 503 sin filtrar el error cuando un componente falla', function () {
    $this->mock(HealthChecker::class, function (MockInterface $mock): void {
        $mock->shouldReceive('check')->once()->andReturn([
            'database' => false,
            'cache' => true,
        ]);
        $mock->shouldReceive('passes')->once()->andReturn(false);
    });

    $this->get('/up')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'unavailable']);
});

it('health:check sale con 0 cuando todo responde', function () {
    $this->artisan('health:check')
        ->expectsOutput('database: ok')
        ->expectsOutput('cache: ok')
        ->assertSuccessful();
});

it('health:check sale con 1 cuando un componente falla', function () {
    $this->mock(HealthChecker::class, function (MockInterface $mock): void {
        $mock->shouldReceive('check')->once()->andReturn([
            'database' => false,
            'cache' => true,
        ]);
        $mock->shouldReceive('passes')->once()->andReturn(false);
    });

    $this->artisan('health:check')
        ->expectsOutput('database: fail')
        ->expectsOutput('cache: ok')
        ->assertFailed();
});

it('reutiliza el resultado cacheado durante la ventana corta', function () {
    Cache::put(HealthChecker::CACHE_KEY, ['database' => false, 'cache' => true], 10);

    // La base y la caché reales responden, así que este resultado solo puede
    // venir de la caché.
    expect(app(HealthChecker::class)->check())
        ->toBe(['database' => false, 'cache' => true]);
});

it('sirve el resultado cacheado en /up', function () {
    Cache::put(HealthChecker::CACHE_KEY, ['database' => false, 'cache' => true], 10);

    $this->get('/up')
        ->assertStatus(503)
        ->assertExactJson(['status' => 'unavailable']);
});

it('permite leer en directo con check(fresh: true)', function () {
    Cache::put(HealthChecker::CACHE_KEY, ['database' => false, 'cache' => false], 10);

    expect(app(HealthChecker::class)->check(fresh: true))
        ->toBe(['database' => true, 'cache' => true]);
});

it('health:check --fresh ignora el resultado cacheado', function () {
    Cache::put(HealthChecker::CACHE_KEY, ['database' => false, 'cache' => false], 10);

    $this->artisan('health:check', ['--fresh' => true])
        ->expectsOutput('database: ok')
        ->expectsOutput('cache: ok')
        ->assertSuccessful();
});

it('devuelve fail en vez de 500 cuando el store de caché lanza', function () {
    Cache::shouldReceive('get')->andThrow(new RuntimeException('cache down'));
    Cache::shouldReceive('put')->andThrow(new RuntimeException('cache down'));
    Cache::shouldReceive('forget')->andThrow(new RuntimeException('cache down'));

    $checks = app(HealthChecker::class)->check();

    expect($checks['cache'])->toBeFalse()
        ->and($checks['database'])->toBeTrue();
});
