<?php 

namespace App\Services\Auth;

use App\Models\Users\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class AuthService{
    public function login(string $email, string $password) : string {
        if(!Auth::attempt(['email' => $email, 'password' => $password])){
            throw new AuthenticationException('Incorrect Credentials');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->createToken('api-token')->plainTextToken;
    }

    public function logout(User $user) : void {
         /** @var \App\Models\User $user */
        $user->currentAccessToken()->delete();
    }

    public function refresh(User $user) : string {
        /** @var \App\Models\User $user */
        $user->currentAccessToken()->delete();

        return $user->createToken('api_token')->plainTextToken;
    }
}