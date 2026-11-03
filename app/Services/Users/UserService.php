<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Enums\Users\RoleEnum;
use App\Exceptions\Users\LastAdminException;
use App\Models\Users\Role;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserService
{
    private const RELATIONS = [
        'roles.permissions',
        'branches.city.state.country',
    ];

    public function getAllPaginated(?int $perPage = null)
    {
        return User::with(self::RELATIONS)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getUser(User $user): User
    {
        return $user->load(self::RELATIONS);
    }

    public function createUser(UserData $data): User
    {

        return DB::transaction(function () use ($data) {
            $arrayData = [
                'name' => $data->name,
                'last_name' => $data->lastName,
                'document_number' => $data->documentNumber,
                'code' => $data->code,
                'email' => $data->email,
                'password' => $data->password,
                'is_active' => $data->isActive,
                // Las cuentas creadas desde la administración nacen con una
                // clave temporal que el empleado debe reemplazar al entrar.
                'must_change_password' => true,
            ];

            $cleanData = array_filter($arrayData, function ($value) {
                return $value !== null;
            });

            $user = User::create($cleanData);

            if (! empty($data->roles)) {
                $user->roles()->sync($data->roles);
            }

            if (! empty($data->branches)) {
                $user->branches()->sync($data->branches);
            }

            $user->refresh();
            $user->forgetPermissionCache();

            return $user->load(self::RELATIONS);
        });
    }

    public function updateUser(User $user, UserData $data): User
    {

        return DB::transaction(function () use ($user, $data) {
            $this->guardLastActiveAdmin($user, $data);

            // `code` no forma parte de la actualización: es inmutable y el
            // FormRequest ya rechaza cualquier valor distinto del actual.
            $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

            $cleanData = array_filter($arrayData, function ($value) {
                return $value !== null;
            });

            $user->update($cleanData);

            // Una contraseña nueva o una cuenta desactivada invalidan las
            // sesiones abiertas para que el cambio tenga efecto inmediato.
            if ($data->password !== null || $data->isActive === false) {
                $user->tokens()->delete();
            }

            if ($data->roles !== null) {
                $user->roles()->sync($data->roles);
            }

            if ($data->branches !== null) {
                $user->branches()->sync($data->branches);
            }

            // Los roles pueden haber cambiado: la siguiente petición debe
            // resolver los permisos contra la base de datos.
            $user->forgetPermissionCache();

            return $user->load(self::RELATIONS);
        });
    }

    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            if ($user->is_active && $this->isAdmin($user)) {
                $this->ensureAnotherActiveAdminExists();
            }

            $user->tokens()->delete();
            $user->forgetPermissionCache();

            return $user->delete();
        });
    }

    /**
     * Asigna una contraseña temporal y obliga al usuario a cambiarla.
     */
    public function resetPassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->update([
                'password' => $password,
                'must_change_password' => true,
            ]);

            $user->tokens()->delete();
            $user->forgetPermissionCache();
        });
    }

    /**
     * Impide desactivar o quitar el rol `admin` al último administrador activo.
     */
    private function guardLastActiveAdmin(User $user, UserData $data): void
    {
        if (! $user->is_active || ! $this->isAdmin($user)) {
            return;
        }

        $deactivating = $data->isActive === false;
        $removingAdminRole = $data->roles !== null && ! $this->containsAdminRole($data->roles);

        if ($deactivating || $removingAdminRole) {
            $this->ensureAnotherActiveAdminExists();
        }
    }

    /**
     * Bloquea las filas de los administradores activos y exige que quede más
     * de uno, de modo que dos desactivaciones concurrentes no dejen el sistema
     * sin administración.
     */
    private function ensureAnotherActiveAdminExists(): void
    {
        $activeAdmins = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RoleEnum::ADMIN->value))
            ->lockForUpdate()
            ->pluck('id');

        if ($activeAdmins->count() <= 1) {
            throw LastAdminException::cannotRemoveLastActiveAdmin();
        }
    }

    private function isAdmin(User $user): bool
    {
        return $user->roles()->where('name', RoleEnum::ADMIN->value)->exists();
    }

    /**
     * @param  array<array-key, mixed>  $roleIds
     */
    private function containsAdminRole(array $roleIds): bool
    {
        $adminRoleId = (int) Role::where('name', RoleEnum::ADMIN->value)->value('id');

        foreach ($roleIds as $roleId) {
            if ((int) $roleId === $adminRoleId) {
                return true;
            }
        }

        return false;
    }
}
