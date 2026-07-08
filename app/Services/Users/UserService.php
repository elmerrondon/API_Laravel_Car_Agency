<?php 

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Models\Users\User;

class UserService{

    public function getAllPaginated(?int $perPage = null){
        return User::paginate($perPage);
    }

    public function createUser(UserData $data) : User{
        $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $user = User::create($cleanData);

        if(!empty($data->roles)){
            $user->roles()->attach($data->roles);
        }

        $user->refresh();

        return $user->load('roles');
    }

    public function updateUser(User $user, UserData $data) : User{
        $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $user->update($cleanData);

        if($data->roles !== null){
            $user->roles()->sync($data->roles);
        }

        return $user->load('roles');
    }

    public function deleteUser(User $user) : bool{
        return $user->delete();
    }
}