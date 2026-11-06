<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Enums\Audit\AuditAction;
use App\Models\Users\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

class UserService
{
    private const RELATIONS = [
        'roles.permissions',
        'branches.city.state.country',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly UserAdminGuard $guard,
    ) {}

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

        $user = DB::transaction(function () use ($data) {
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

        $this->audit->record(
            AuditAction::USER_CREATED,
            $user,
            [],
            $this->userAuditValues($user),
        );

        return $user;
    }

    public function updateUser(User $user, UserData $data): User
    {
        $oldAttributes = $this->userAttributes($user);
        $oldRoles = $this->roleIds($user);
        $oldBranches = $this->branchIds($user);

        $user = DB::transaction(function () use ($user, $data) {
            $this->guard->guardUpdate($user, $data);

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

        $newAttributes = $this->userAttributes($user);
        $newRoles = $this->roleIds($user);
        $newBranches = $this->branchIds($user);

        $oldChanged = [];
        $newChanged = [];

        foreach ($newAttributes as $attribute => $value) {
            if ($oldAttributes[$attribute] !== $value) {
                $oldChanged[$attribute] = $oldAttributes[$attribute];
                $newChanged[$attribute] = $value;
            }
        }

        if ($oldBranches !== $newBranches) {
            $oldChanged['branches'] = $oldBranches;
            $newChanged['branches'] = $newBranches;
        }

        if ($oldChanged !== []) {
            $this->audit->record(AuditAction::USER_UPDATED, $user, $oldChanged, $newChanged);
        }

        if ($oldRoles !== $newRoles) {
            $this->audit->record(
                AuditAction::USER_ROLES_CHANGED,
                $user,
                ['roles' => $oldRoles],
                ['roles' => $newRoles],
            );
        }

        if ($oldAttributes['is_active'] !== $newAttributes['is_active']) {
            $this->audit->record(
                $newAttributes['is_active'] ? AuditAction::USER_ACTIVATED : AuditAction::USER_DEACTIVATED,
                $user,
                ['is_active' => $oldAttributes['is_active']],
                ['is_active' => $newAttributes['is_active']],
            );
        }

        if ($data->password !== null) {
            $this->audit->record(
                AuditAction::USER_PASSWORD_CHANGED,
                $user,
                ['must_change_password' => $oldAttributes['must_change_password']],
                ['must_change_password' => $newAttributes['must_change_password']],
            );
        }

        return $user;
    }

    public function deleteUser(User $user): bool
    {
        $oldValues = $this->userAuditValues($user);

        $deleted = DB::transaction(function () use ($user) {
            $this->guard->guardDeletion($user);

            $user->tokens()->delete();
            $user->forgetPermissionCache();

            return $user->delete();
        });

        $this->audit->record(AuditAction::USER_DELETED, $user, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function userAuditValues(User $user): array
    {
        return $this->userAttributes($user) + [
            'roles' => $this->roleIds($user),
            'branches' => $this->branchIds($user),
        ];
    }

    /**
     * @return array{name: string, last_name: string, document_number: string, code: string, email: string, is_active: bool, must_change_password: bool}
     */
    private function userAttributes(User $user): array
    {
        return [
            'name' => (string) $user->name,
            'last_name' => (string) $user->last_name,
            'document_number' => (string) $user->document_number,
            'code' => (string) $user->code,
            'email' => (string) $user->email,
            'is_active' => (bool) $user->is_active,
            'must_change_password' => (bool) $user->must_change_password,
        ];
    }

    /**
     * @return list<int>
     */
    private function roleIds(User $user): array
    {
        return array_map(
            static fn (mixed $id): int => (int) $id,
            $user->roles()->pluck('roles.id')->all(),
        );
    }

    /**
     * @return list<int>
     */
    private function branchIds(User $user): array
    {
        return array_map(
            static fn (mixed $id): int => (int) $id,
            $user->branches()->pluck('branches.id')->all(),
        );
    }
}
