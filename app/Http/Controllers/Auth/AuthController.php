<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginAuthRequest;
use App\Http\Resources\Users\AuthenticatedUserResource;
use App\Models\Users\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function login(LoginAuthRequest $request)
    {
        $email = (string) $request->validated('email');
        $password = (string) $request->validated('password');

        $result = $this->service->login($email, $password);

        return response()->json([
            'message' => 'Success Login',
            'token' => $result['token'],
            'must_change_password' => $result['must_change_password'],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return new AuthenticatedUserResource($this->service->profile($user));
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $this->service->changePassword(
            $user,
            (string) $request->validated('current_password'),
            (string) $request->validated('password'),
        );

        return response()->json(['message' => 'Password Updated']);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $this->service->logout($user);

        return response()->json(['message' => 'Logout Success']);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();

        $newToken = $this->service->refresh($user);

        return response()->json(['message' => 'Token Refresh Success', 'token' => $newToken]);
    }
}
