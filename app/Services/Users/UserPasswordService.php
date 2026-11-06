<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Audit\AuditAction;
use App\Models\Users\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;

/**
 * Gestión de credenciales administrativas: asignación de claves temporales.
 */
final class UserPasswordService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Asigna una contraseña temporal y obliga al usuario a cambiarla.
     */
    public function resetPassword(User $user, string $password): void
    {
        $oldMustChangePassword = $user->must_change_password;

        DB::transaction(function () use ($user, $password): void {
            $user->update([
                'password' => $password,
                'must_change_password' => true,
            ]);

            $user->tokens()->delete();
            $user->forgetPermissionCache();
        });

        $this->audit->record(
            AuditAction::USER_PASSWORD_RESET,
            $user,
            ['must_change_password' => $oldMustChangePassword],
            ['must_change_password' => true],
        );
    }
}
