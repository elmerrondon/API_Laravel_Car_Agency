<?php

declare(strict_types=1);

use App\Services\Health\HealthChecker;
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
