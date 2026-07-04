<?php 

namespace App\Services\Users;

use App\DTOs\Users\RoleData;
use App\Enums\Users\RoleEnum;
use App\Models\Users\Role;

class RoleService{
    public function getAllPaginated(?int $perPage = null){
        return Role::paginate($perPage);
    }

    public function create(RoleData $data){

        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($data->name, $systemRoles)){
            return response()->json(['message' => 'Accion denegada. No se puede crear este rol por que ya existe en el sistema'], 403);
        }

        $arrayData = ['name' => $data->name, 'description' => $data->description];;
        
        $role = Role::create($arrayData);

        return $role;
    }

    public function update(Role $role, RoleData $data) {
        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($role->name, $systemRoles)){
            return response()->json(['message' => 'Accion denegada. No se puede editar este rol del sistema'], 403);
        }

        $arrayData = ['name' => $data->name, 'description' => $data->description];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $role->update($cleanData);

        return $role;
    }

    public function delete(Role $role){
        $systemRoles = array_column(RoleEnum::cases(), 'value');

        if(in_array($role->name, $systemRoles)){
            return response()->json(['message' => 'Accion denegada. No se puede eliminar este rol del sistema'], 403);
        }

        return response()->noContent();
    }
}