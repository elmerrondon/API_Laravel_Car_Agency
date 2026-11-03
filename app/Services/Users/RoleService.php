<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTOs\Users\RoleData;
use App\Enums\Audit\AuditAction;
use App\Enums\Users\RoleEnum;
use App\Exceptions\Users\RoleInUseException;
use App\Models\Users\Role;
use App\Models\Users\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class RoleService
{
    public function __construct(private readonly AuditService $audit) {}

    public function getAllPaginatedRoles(?int $perPage = null)
    {
        return Role::with('permissions')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createRole(RoleData $data)
    {

        $role = DB::transaction(function () use ($data) {
            $this->ensurePermissionsAreNotEmpty($data->permissions);

            $systemRoles = array_column(RoleEnum::cases(), 'value');

            if (in_array($data->name, $systemRoles)) {
                throw new LogicException('The Role cannot be created because it already exists and is a base system role');
            }

            $arrayData = ['name' => $data->name, 'description' => $data->description];

            $role = Role::create($arrayData);

            if (! empty($data->permissions)) {
                $role->permissions()->sync($data->permissions);
            }

            return $role->load('permissions');
        });

        $this->audit->record(AuditAction::ROLE_CREATED, $role, [], $this->roleAuditValues($role));

        return $role;
    }

    public function updateRole(Role $role, RoleData $data)
    {
        $oldValues = $this->roleAuditValues($role);

        $role = DB::transaction(function () use ($role, $data) {
            if ($data->permissions !== null) {
                $this->ensurePermissionsAreNotEmpty($data->permissions);
            }

            $systemRoles = array_column(RoleEnum::cases(), 'value');

            if (in_array($role->name, $systemRoles)) {
                throw new LogicException('The role cannot be updated because it is a base system role');
            }

            $arrayData = ['name' => $data->name, 'description' => $data->description];

            $cleanData = array_filter($arrayData, function ($value) {
                return $value !== null;
            });

            $role->update($cleanData);

            if ($data->permissions !== null) {
                $role->permissions()->sync($data->permissions);
            }

            // La matriz del rol cambió: invalidar a sus usuarios.
            User::flushPermissionCacheForRole((int) $role->getKey());

            return $role->load('permissions');
        });

        $this->audit->record(AuditAction::ROLE_UPDATED, $role, $oldValues, $this->roleAuditValues($role));

        return $role;
    }

    /**
     * Un rol sin permisos es inoperante y deja usuarios bloqueados sin que se
     * note: el FormRequest ya lo valida, pero el servicio lo defiende también.
     *
     * @param  array<array-key, mixed>|null  $permissions
     */
    private function ensurePermissionsAreNotEmpty(?array $permissions): void
    {
        if (! empty($permissions)) {
            return;
        }

        throw ValidationException::withMessages([
            'permissions' => ['A role must have at least one permission.'],
        ]);
    }

    public function deleteRole(Role $role): bool
    {
        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if (in_array($role->name, $systemRoles)) {
            throw new LogicException('The role cannot be deleted because it is a base system role');
        }

        if ($role->users()->exists()) {
            throw RoleInUseException::assignedToUsers();
        }

        $oldValues = $this->roleAuditValues($role);

        $deleted = $role->delete();

        $this->audit->record(AuditAction::ROLE_DELETED, $role, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array{name: string, description: string, permissions: list<int>}
     */
    private function roleAuditValues(Role $role): array
    {
        return [
            'name' => (string) $role->name,
            'description' => (string) $role->description,
            'permissions' => array_map(
                static fn (mixed $id): int => (int) $id,
                $role->permissions()->pluck('permissions.id')->all(),
            ),
        ];
    }
}
