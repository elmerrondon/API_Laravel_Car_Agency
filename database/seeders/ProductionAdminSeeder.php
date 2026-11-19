<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Users\RoleEnum;
use App\Exceptions\Users\InsecureAdminCredentialsException;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\Support\PasswordPolicy;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Datos fundacionales de producción.
 *
 * A diferencia de los seeders demo, aquí no se usa Faker ni `fake()`: solo se
 * crean roles/permisos (necesarios para que el RBAC funcione) y el usuario
 * administrador configurado por variables de entorno. Gracias a esto la imagen
 * de producción (`composer install --no-dev`) puede ejecutar
 * `php artisan migrate --force --seed`.
 */
class ProductionAdminSeeder extends Seeder
{
    /**
     * Contraseña de conveniencia para local/testing. En producción la clave es
     * obligatoria y debe cumplir la política fuerte: aquí solo se usa cuando
     * el seeder se ejecuta fuera de producción sin definirla.
     */
    private const DEFAULT_PASSWORD = 'secret1234';

    /**
     * Email de conveniencia para local/testing. En producción se exige uno real.
     */
    private const DEFAULT_EMAIL = 'admin@agency.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = trim((string) config('seeders.admin_email'));
        $password = (string) config('seeders.admin_password');

        // Validar antes de escribir roles/permisos: un fallo de configuración
        // no debe dejar la base a medio sembrar.
        if (app()->isProduction()) {
            $this->assertSecureCredentials($email, $password);
        } elseif ($password === '') {
            $password = self::DEFAULT_PASSWORD;
        }

        // RBAC: sin roles ni permisos, el administrador no podría hacer nada.
        // Estos seeders son estáticos (no dependen de Faker) y son idempotentes.
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // `code` es la identidad estable del registro: permite cambiar el email
        // entre despliegues sin crear un administrador duplicado. `withTrashed`
        // evita chocar con la unicidad global de `users.code` si el usuario fue
        // borrado lógicamente. La clave se normaliza aquí porque el mutador del
        // modelo no interviene en el `where` de `updateOrCreate`.
        $user = User::withTrashed()->updateOrCreate(
            ['code' => Str::upper(trim((string) config('seeders.admin_code')))],
            [
                'name' => (string) config('seeders.admin_name'),
                'last_name' => (string) config('seeders.admin_last_name'),
                'document_number' => (string) config('seeders.admin_document_number'),
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => false,
            ],
        );

        if ($user->trashed()) {
            $user->restore();
        }

        $role = Role::where('name', RoleEnum::ADMIN->value)->firstOrFail();
        $user->roles()->sync([$role->id]);

        // Si la instalación ya tiene sucursales, el administrador queda
        // asociado a la primera activa; si no, el usuario sigue siendo válido.
        $branch = Branch::where('is_active', true)->orderBy('id')->first();

        if ($branch) {
            $user->branches()->sync([$branch->id]);
        }

        if (! app()->isProduction() && $password === self::DEFAULT_PASSWORD) {
            $this->warn(
                'ADMIN_PASSWORD no está definida: se usó la contraseña de local/testing. '
                .'En producción el seeder aborta si no defines una clave segura.'
            );
        }

        if (! app()->isProduction() && $email === self::DEFAULT_EMAIL) {
            $this->warn('ADMIN_EMAIL no está definida: se usó admin@agency.com de local/testing.');
        }
    }

    /**
     * Falla rápido si el administrador fundacional no es seguro.
     *
     * Se ejecuta solo con `APP_ENV=production`, antes de tocar la base de datos.
     */
    private function assertSecureCredentials(string $email, string $password): void
    {
        if ($email === '' || str_starts_with($email, 'CHANGE_ME_') || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw InsecureAdminCredentialsException::email();
        }

        if ($password === '') {
            throw InsecureAdminCredentialsException::missingPassword();
        }

        if ($password === self::DEFAULT_PASSWORD) {
            throw InsecureAdminCredentialsException::defaultPassword();
        }

        if (str_starts_with($password, 'CHANGE_ME_')) {
            throw InsecureAdminCredentialsException::placeholderPassword();
        }

        $validator = Validator::make(
            ['password' => $password],
            ['password' => PasswordPolicy::strong()],
        );

        if ($validator->fails()) {
            throw InsecureAdminCredentialsException::weakPassword();
        }
    }

    /**
     * Muestra una advertencia solo cuando el seeder corre desde `db:seed`.
     */
    private function warn(string $message): void
    {
        if ($this->command === null) {
            return;
        }

        $this->command->warn($message);
    }
}
