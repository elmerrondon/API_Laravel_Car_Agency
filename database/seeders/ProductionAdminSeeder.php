<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Users\RoleEnum;
use App\Models\Locations\Branch;
use App\Models\Users\Role;
use App\Models\Users\User;
use Database\Seeders\Users\PermissionSeeder;
use Database\Seeders\Users\RolePermissionSeeder;
use Database\Seeders\Users\RoleSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
     * Contraseña por defecto de `config/seeders.php`. Solo sirve para el primer
     * arranque; se avisa cuando se usa para que se cambie de inmediato.
     */
    private const DEFAULT_PASSWORD = 'secret1234';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // RBAC: sin roles ni permisos, el administrador no podría hacer nada.
        // Estos seeders son estáticos (no dependen de Faker) y son idempotentes.
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $email = (string) config('seeders.admin_email');
        $password = (string) config('seeders.admin_password');

        // `code` es la identidad estable del registro: permite cambiar el email
        // entre despliegues sin crear un administrador duplicado. `withTrashed`
        // evita chocar con la unicidad global de `users.code` si el usuario fue
        // borrado lógicamente.
        $user = User::withTrashed()->updateOrCreate(
            ['code' => (string) config('seeders.admin_code')],
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

        if ($password === self::DEFAULT_PASSWORD) {
            $this->warn(
                'ADMIN_PASSWORD no está definida: se usó la contraseña por defecto. '
                .'Cámbiala en la plataforma y vuelve a ejecutar el seeder.'
            );
        }

        if ($email === 'admin@agency.com') {
            $this->warn('ADMIN_EMAIL no está definida: se usó admin@agency.com por defecto.');
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
