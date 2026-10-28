<?php

use App\Enums\Users\RoleEnum;
use App\Models\Locations\Branch;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    actingAsRole(RoleEnum::ADMIN);
});

it('rechaza una descripción de sucursal de más de 300 caracteres', function () {
    $branch = Branch::firstOrFail();

    $this->patchJson("/api/branches/{$branch->id}", [
        'description' => str_repeat('a', 301),
    ])->assertUnprocessable()->assertJsonValidationErrors('description');
});
