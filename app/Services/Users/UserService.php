<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    private const RELATIONS = [
        'roles.permissions',
        'branches.city.state.country',
    ];

    public function getAllPaginated(?int $perPage = null)
    {
        return User::with(self::RELATIONS)->paginate($perPage);
    }

    public function getUser(User $user): User
    {
        return $user->load(self::RELATIONS);
    }

    public function createUser(UserData $data): User
    {

        return DB::transaction(function () use ($data) {
            $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

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

            return $user->load(self::RELATIONS);
        });
    }

    public function updateUser(User $user, UserData $data): User
    {

        return DB::transaction(function () use ($user, $data) {
            $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

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

            return $user->load(self::RELATIONS);
        });
    }

    public function deleteUser(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            $user->tokens()->delete();

            return $user->delete();
        });
    }
}
