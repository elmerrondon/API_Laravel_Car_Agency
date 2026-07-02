<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\Auth\LoginAuthRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;


class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service)
    {
        
    }

    public function login(LoginAuthRequest $request){
        $email = $request->validated('email');
        $pasword = $request->validated('password');

        $token = $this->service->login($email,$pasword);

        return response()->json(['message' => 'Success Login', 'token' => $token]);
    }

    public function logout(Request $request){
        $user = $request->user();

        $this->service->logout($user);

        return response()->json(['message' => 'Logout Success']);
    }

    public function refresh(Request $request){
        $user = $request->user();
        
        $newToken = $this->service->refresh($user);

        return response()->json(['message' => 'Token Refresh Success', 'token' => $newToken]);
    }
}
