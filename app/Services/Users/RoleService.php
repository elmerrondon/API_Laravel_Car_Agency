<?php 

namespace App\Services\Users;

use App\DTOs\Users\RoleData;
use App\Models\Users\Role;

class RoleService{
    public function getAllPaginated(?int $perPage = null){
        return Role::paginate($perPage);
    }

    public function create(RoleData $data) : Role{
        $arrayData = ['name' => $data->name, 'description' => $data->description];;
        
        $role = Role::create($arrayData);

        return $role;
    }

    public function update(Role $role, RoleData $data) : Role{
        $arrayData = ['name' => $data->name, 'description' => $data->description];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $role->update($cleanData);

        return $role;
    }

    public function delete(Role $role) : bool{
        return $role->delete();
    }
}