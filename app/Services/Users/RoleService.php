<?php 

namespace App\Services\Users;

use App\DTOs\Users\RoleData;
use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;

class RoleService{
    public function getAllPaginated(?int $perPage = null){
        return Role::paginate($perPage);
    }

    public function createRole(RoleData $data){

        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($data->name, $systemRoles)){
            return false;
        }

        $arrayData = ['name' => $data->name, 'description' => $data->description];;
        
        $role = Role::create($arrayData);

        return $role;
    }

    public function updateRole(Role $role, RoleData $data) {
        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($role->name, $systemRoles)){
            return false;
        }

        $arrayData = ['name' => $data->name, 'description' => $data->description];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $role->update($cleanData);

        return $role;
    }

    public function deleteRole(Role $role) : bool{
        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($role->name, $systemRoles)){
            return false;
        }

        return $role->delete();
    }
}