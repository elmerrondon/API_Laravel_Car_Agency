<?php

declare(strict_types=1);

use App\Enums\Users\RoleEnum;
use App\Exceptions\Users\InsecureAdminCredentialsException;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\ProductionAdminSeeder;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Administrador fundacional de producción
|--------------------------------------------------------------------------
|
| El seeder debe fallar antes de escribir nada si las credenciales no son
| seguras. Fuera de producción conserva el fallback de conveniencia.
|
*/

function configureProductionAdminCredentials(array $overrides = []): void
{
    config([
        'seeders.admin_name' => 'Root',
        'seeders.admin_last_name' => 'Producción',
        'seeders.admin_document_number' => 'V-90000001',
        'seeders.admin_code' => 'USR-9001',
        'seeders.admin_email' => 'root@agency.test',
        'seeders.admin_password' => 'S3gura-2026',
        ...$overrides,
    ]);
}

function runProductionAdminSeederAsProduction(): void
{
    app()['env'] = 'production';

    // Se invoca el seeder directamente: `$this->seed()` ejecuta el comando
    // `db:seed`, que en producción pide confirmación interactiva y enmascara
    // la excepción de validación que queremos comprobar.
    app(ProductionAdminSeeder::class)->run();
}

it('aborta en producción si falta la contraseña del administrador', function () {
    configureProductionAdminCredentials(['seeders.admin_password' => null]);

    expect(fn () => runProductionAdminSeederAsProduction())
        ->toThrow(InsecureAdminCredentialsException::class, 'ADMIN_PASSWORD no está definido');

    expect(User::count())->toBe(0)
        ->and(Role::count())->toBe(0);
});

it('rechaza la contraseña por defecto en producción', function () {
    configureProductionAdminCredentials(['seeders.admin_password' => 'secret1234']);

    expect(fn () => runProductionAdminSeederAsProduction())
        ->toThrow(InsecureAdminCredentialsException::class, 'secret1234');

    expect(User::count())->toBe(0);
});

it('rechaza un marcador CHANGE_ME_ en la contraseña de producción', function () {
    configureProductionAdminCredentials(['seeders.admin_password' => 'CHANGE_ME_ADMIN_PASSWORD']);

    expect(fn () => runProductionAdminSeederAsProduction())
        ->toThrow(InsecureAdminCredentialsException::class, 'CHANGE_ME_');
});

it('rechaza contraseñas que no cumplen la política fuerte', function (string $password) {
    configureProductionAdminCredentials(['seeders.admin_password' => $password]);

    expect(fn () => runProductionAdminSeederAsProduction())
        ->toThrow(InsecureAdminCredentialsException::class, 'política de producción');
})->with([
    'corta' => ['Ab1!'],
    'sin símbolo' => ['Abcdefg1'],
    'sin mayúscula' => ['abcdefg1!'],
    'sin número' => ['Abcdefg!'],
]);

it('exige un email real para el administrador en producción', function (?string $email) {
    configureProductionAdminCredentials(['seeders.admin_email' => $email]);

    expect(fn () => runProductionAdminSeederAsProduction())
        ->toThrow(InsecureAdminCredentialsException::class, 'ADMIN_EMAIL');
})->with([
    'ausente' => [null],
    'marcador' => ['CHANGE_ME_ADMIN_EMAIL'],
    'inválido' => ['no-es-un-email'],
]);

it('crea el administrador en producción cuando las credenciales son seguras', function () {
    configureProductionAdminCredentials();

    runProductionAdminSeederAsProduction();

    $user = User::where('email', 'root@agency.test')->firstOrFail();

    expect(Hash::check('S3gura-2026', $user->password))->toBeTrue()
        ->and($user->is_active)->toBeTrue()
        ->and($user->roles()->pluck('name')->all())->toBe([RoleEnum::ADMIN->value]);
});

it('conserva el fallback de local/testing cuando no se define contraseña', function () {
    configureProductionAdminCredentials(['seeders.admin_password' => null]);

    $this->seed(ProductionAdminSeeder::class);

    $user = User::where('email', 'root@agency.test')->firstOrFail();

    expect(Hash::check('secret1234', $user->password))->toBeTrue();
});
