<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Enums\Users\RoleEnum;
use App\Exceptions\Users\LastAdminException;
use App\Models\Users\Role;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Guardas que impiden dejar el sistema sin ningún administrador activo.
 */
final class UserAdminGuard
{
    /**
     * Impide desactivar o quitar el rol `admin` al último administrador activo.
     */
    public function guardUpdate(User $user, UserData $data): void
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
     * Impide borrar al último administrador activo.
     */
    public function guardDeletion(User $user): void
    {
        if ($user->is_active && $this->isAdmin($user)) {
            $this->ensureAnotherActiveAdminExists();
        }
    }

    public function isAdmin(User $user): bool
    {
        return $user->roles()->where('name', RoleEnum::ADMIN->value)->exists();
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
