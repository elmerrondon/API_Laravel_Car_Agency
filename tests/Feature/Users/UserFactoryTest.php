<?php

use App\Models\Users\User;
use Illuminate\Support\Facades\Hash;

it('crea usuarios con la factory', function () {
    $user = User::factory()->create();

    expect($user->exists)->toBeTrue()
        ->and($user->code)->toStartWith('USR-')
        ->and($user->document_number)->toHaveLength(10)
        ->and($user->is_active)->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeTrue();

    $this->assertDatabaseHas('users', ['email' => $user->email]);
});

it('permite sobrescribir atributos de la factory', function () {
    $user = User::factory()->create([
        'email' => 'custom@example.test',
        'is_active' => false,
    ]);

    expect($user->email)->toBe('custom@example.test')
        ->and($user->is_active)->toBeFalse();
});
