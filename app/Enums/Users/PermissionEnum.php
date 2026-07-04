<?php

namespace App\Enums\Users;

enum PermissionEnum : string
{
    case VIEW_CARS = 'view_cars';
    case CREATE_CARS = 'created_cars';
    case UPDATE_CARS = 'update_cars';
    case DELETE_CARS = 'delete_cars';

    public function description() : string{
        return match($this){
            self::VIEW_CARS => 'Ver el catálogo general de vehículos',
            self::CREATE_CARS => 'Ingresar nuevos vehículos al sistema',
            self::UPDATE_CARS => 'Editar datos de los vehículos',
            self::DELETE_CARS => 'Dar de baja vehículos del inventario'
        };
    }
}
