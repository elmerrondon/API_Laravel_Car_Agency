<?php 

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Models\Users\User;

class UserService{

    public function getAllPaginated(?int $perPage = null){
        return User::paginate($perPage);
    }

    public function create(UserData $data) : User{
        $arrayData = ['name' => $data->name, 'lastname' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $user = User::create($cleanData);

        $user->refresh();

        return $user;
    }

    public function update(User $user, UserData $data) : User{
        $arrayData = ['name' => $data->name, 'lastname' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $user->update($cleanData);

        return $user;
    }

    public function delete(User $user) : bool{
        return $user->delete();
    }
}