<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Users\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private const PROFILE_RELATIONS = [
        'roles.permissions',
        'branches.city.state.country',
    ];

    /**
     * @return array{token: string, must_change_password: bool}
     */
    public function login(string $email, string $password): array
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true])) {
            throw new AuthenticationException('Incorrect Credentials');
        }

        /** @var User $user */
        $user = Auth::user();

        return [
            'token' => $user->createToken('api-token')->plainTextToken,
            // Es informativo: el login no se bloquea, pero el frontend puede
            // forzar el cambio antes de dejar operar al empleado.
            'must_change_password' => $user->must_change_password,
        ];
    }

    public function profile(User $user): User
    {
        return $user->load(self::PROFILE_RELATIONS);
    }

    /**
     * Cambia la contraseña propia verificando la actual contra el usuario de
     * Sanctum (el guard `web` por defecto no está autenticado en la API).
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password does not match your current password.'],
            ]);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $user->update([
                'password' => $newPassword,
                'must_change_password' => false,
            ]);

            /** @var PersonalAccessToken|null $token */
            $token = $user->currentAccessToken();
            $currentTokenId = $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;

            // Las demás sesiones quedan invalidadas; la petición en curso
            // conserva su token para no expulsar al usuario que cambió la clave.
            if ($currentTokenId !== null) {
                $user->tokens()->whereKeyNot($currentTokenId)->delete();
            } else {
                $user->tokens()->delete();
            }
        });
    }

    public function logout(User $user): void
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        $token?->delete();
    }

    public function refresh(User $user): string
    {
        /** @var PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        $token?->delete();

        return $user->createToken('api_token')->plainTextToken;
    }
}
