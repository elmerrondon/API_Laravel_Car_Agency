<?php 

namespace App\Services\Users;

use App\DTOs\Users\UserData;
use App\Models\Users\User;
use Illuminate\Support\Facades\DB;

class UserService{

    public function getAllPaginated(?int $perPage = null){
        return User::with(['roles','branches'])->paginate($perPage);
    }

    public function createUser(UserData $data) : User{

      return DB::transaction(function () use  ($data){
        $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $user = User::create($cleanData);

        if(!empty($data->roles)){
            $user->roles()->sync($data->roles);
        }

        if(!empty($data->branches)){
            $user->branches()->sync($data->branches);
        }

        $user->refresh();

        return $user->load(['roles','branches']);
      });
    }

    public function updateUser(User $user, UserData $data) : User{

        return DB::transaction(function () use ($user, $data){
            $arrayData = ['name' => $data->name, 'last_name' => $data->lastName, 'document_number' => $data->documentNumber, 'code' => $data->code, 'email' => $data->email, 'password' => $data->password, 'is_active' => $data->isActive];

             $cleanData = array_filter($arrayData, function ($value) {
                 return $value !== null;
             });
     
             $user->update($cleanData);
     
             if($data->roles !== null){
                 $user->roles()->sync($data->roles);
             }
     
             if($data->branches !== null){
                 $user->branches()->sync($data->branches);
             }
     
             return $user->load(['roles','branches']);
           });
    }
        

    public function deleteUser(User $user) : bool{
        return $user->delete();
    }
}