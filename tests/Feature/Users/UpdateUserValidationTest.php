<?php

use App\Enums\Users\RoleEnum;
use App\Models\Users\User;

beforeEach(fn () => actingAsRole(RoleEnum::ADMIN));

it('rechaza un document_number ya registrado', function () {
    $original = User::create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'document_number' => 'DOC10001',
        'code' => 'USR-001',
        'email' => 'ana@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $other = User::create([
        'name' => 'Luis',
        'last_name' => 'Gómez',
        'document_number' => 'DOC20002',
        'code' => 'USR-002',
        'email' => 'luis@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->patch("/api/users/{$original->id}", [
        'document_number' => $other->document_number,
    ])->assertUnprocessable()->assertJsonValidationErrors('document_number');
});

it('permite conservar el document_number propio', function () {
    $user = User::create([
        'name' => 'Ana',
        'last_name' => 'Pérez',
        'document_number' => 'DOC10001',
        'code' => 'USR-001',
        'email' => 'ana@example.test',
        'password' => 'password',
        'is_active' => true,
    ]);

    $this->patch("/api/users/{$user->id}", [
        'document_number' => $user->document_number,
    ])->assertOk()->assertJsonPath('data.document_number', $user->document_number);
});
