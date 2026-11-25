<?php

declare(strict_types=1);

use Database\Seeders\DatabaseSeeder;

/**
 * Smoke test integrado en Pest: recorre el flujo real que antes se validaba
 * con un script HTTP temporal (login, perfil, estadísticas, auditoría y logout).
 */
it('recorre el flujo crítico de la API de punta a punta', function () {
    $this->seed(DatabaseSeeder::class);

    $login = $this->postJson('/api/auth/login', [
        'email' => 'admin@caragency.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('must_change_password', false);

    $token = $login->json('token');

    expect($token)->toBeString()->not->toBeEmpty();

    // El login dejó autenticado el guard web y la sesión del cliente de test;
    // se limpian para que las siguientes peticiones usen el token bearer real.
    $this->flushSession();
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@caragency.test');

    $this->withToken($token)->getJson('/api/stats/summary')->assertOk();

    $this->withToken($token)
        ->get('/api/audit-logs/export')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
});
