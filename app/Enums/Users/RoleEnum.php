<?php

namespace App\Enums\Users;

enum RoleEnum : string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case SELLER = 'vendedor';

    public function description() : string{
        return match($this){
            self::ADMIN => 'Administrador global con acceso total al sistema.',
            self::MANAGER => 'Gerente de sucursal con permisos de aprobación.',
            self::SELLER => 'Asesor comercial encargado de las ventas de vehículos.',
        };
    }
}
