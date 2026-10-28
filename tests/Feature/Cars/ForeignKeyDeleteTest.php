<?php

use App\Enums\Users\RoleEnum;
use App\Models\Cars\Car;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    actingAsRole(RoleEnum::ADMIN);
});

it('devuelve 409 al eliminar un color usado por un coche', function () {
    $car = Car::firstOrFail();

    $this->deleteJson("/api/colors/{$car->color_id}")
        ->assertStatus(409)
        ->assertJson([
            'message' => 'The record cannot be deleted because it is referenced by other records.',
        ]);
});
