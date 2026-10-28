<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Users\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function login(string $email, string $password): string
    {
        if (! Auth::attempt(['email' => $email, 'password' => $password, 'is_active' => true])) {
            throw new AuthenticationException('Incorrect Credentials');
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->createToken('api-token')->plainTextToken;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function refresh(User $user): string
    {
        $user->currentAccessToken()->delete();

        return $user->createToken('api_token')->plainTextToken;
    }
}
