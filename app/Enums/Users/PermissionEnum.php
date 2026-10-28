<?php

declare(strict_types=1);

namespace App\Enums\Users;

enum PermissionEnum: string
{
    // Coches
    case VIEW_CARS = 'view_cars';
    case CREATE_CARS = 'create_cars';
    case UPDATE_CARS = 'update_cars';
    case DELETE_CARS = 'delete_cars';

    // Catálogos (coches, monedas, métodos de pago e impuestos)
    case VIEW_CATALOGS = 'view_catalogs';
    case MANAGE_CATALOGS = 'manage_catalogs';

    // Ubicaciones
    case VIEW_LOCATIONS = 'view_locations';
    case MANAGE_LOCATIONS = 'manage_locations';

    // Ventas
    case VIEW_SALES = 'view_sales';
    case CREATE_SALES = 'create_sales';
    case CANCEL_SALES = 'cancel_sales';

    // Usuarios, roles y permisos
    case MANAGE_USERS = 'manage_users';

    // Reportes
    case VIEW_REPORTS = 'view_reports';

    public function description(): string
    {
        return match ($this) {
            self::VIEW_CARS => 'Ver el catálogo general de vehículos',
            self::CREATE_CARS => 'Ingresar nuevos vehículos al sistema',
            self::UPDATE_CARS => 'Editar datos de los vehículos',
            self::DELETE_CARS => 'Dar de baja vehículos del inventario',
            self::VIEW_CATALOGS => 'Consultar los catálogos del sistema',
            self::MANAGE_CATALOGS => 'Crear, editar y eliminar los catálogos del sistema',
            self::VIEW_LOCATIONS => 'Consultar países, estados, ciudades y sucursales',
            self::MANAGE_LOCATIONS => 'Crear, editar y eliminar países, estados, ciudades y sucursales',
            self::VIEW_SALES => 'Consultar las ventas registradas',
            self::CREATE_SALES => 'Registrar nuevas ventas',
            self::CANCEL_SALES => 'Cancelar ventas y liberar el vehículo',
            self::MANAGE_USERS => 'Gestionar usuarios, roles y permisos',
            self::VIEW_REPORTS => 'Descargar reportes del sistema',
        };
    }
}
