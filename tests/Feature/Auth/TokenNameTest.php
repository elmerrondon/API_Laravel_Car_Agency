<?php

declare(strict_types=1);

use App\Models\Users\User;
use App\Services\Auth\AuthService;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->user = User::where('email', 'admin@caragency.test')->firstOrFail();
});

it('registra login y refresh con el mismo nombre de token', function () {
    $token = $this->postJson('/api/auth/login', [
        'email' => 'admin@caragency.test',
        'password' => 'password',
    ])
        ->assertOk()
        ->json('token');

    expect($this->user->tokens()->pluck('name')->all())->toBe([AuthService::TOKEN_NAME]);

    $this->flushSession();
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->postJson('/api/auth/refresh')->assertOk();

    expect($this->user->tokens()->pluck('name')->all())->toBe([AuthService::TOKEN_NAME]);
});
