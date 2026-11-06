<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\License;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

/**
 * Metadatos de la documentación OpenAPI.
 *
 * Scramble deduce el contrato a partir de las rutas, los FormRequest y los
 * Resources, pero no conoce nuestras convenciones: los alias de middleware
 * (`permission:`) ni las respuestas de error de dominio. Este transformador
 * añade esa capa.
 */
final class ApiDocumentation
{
    /**
     * Grupos de la documentación: nombre => [peso de orden, descripción].
     *
     * @var array<string, array{int, string}>
     */
    private const TAGS = [
        'Autenticación' => [0, 'Tokens Bearer de Sanctum: login, cierre de sesión y renovación.'],
        'Coches' => [1, 'Inventario de vehículos y su ciclo de vida.'],
        'Catálogos' => [2, 'Tipos, categorías, marcas, modelos, colores, monedas, métodos de pago e impuestos.'],
        'Ubicaciones' => [3, 'Países, estados, ciudades y sucursales.'],
        'Ventas' => [4, 'Registro y cancelación de ventas, con sus totales en moneda base.'],
        'Reportes' => [5, 'Reportes de inventario y de ventas en JSON o PDF (requieren `view_reports`).'],
        'Usuarios' => [6, 'Usuarios, roles y permisos (requieren `manage_users`).'],
        'Auditoría' => [7, 'Registro de accesos y operaciones críticas (requiere `view_audit_logs`).'],
        'Estadísticas' => [8, 'KPIs, rankings e inventario para el panel de administración (requieren `view_statistics`).'],
    ];

    /**
     * Recursos por segmento de ruta: [grupo, nombre en plural, nombre con artículo].
     *
     * @var array<string, array{string, string, string}>
     */
    private const RESOURCES = [
        'auth' => ['Autenticación', 'sesiones', 'la sesión'],
        'cars' => ['Coches', 'coches', 'el coche'],
        'car-types' => ['Catálogos', 'tipos de coche', 'el tipo de coche'],
        'categories' => ['Catálogos', 'categorías', 'la categoría'],
        'brands' => ['Catálogos', 'marcas', 'la marca'],
        'car-models' => ['Catálogos', 'modelos de coche', 'el modelo de coche'],
        'colors' => ['Catálogos', 'colores', 'el color'],
        'currencies' => ['Catálogos', 'monedas', 'la moneda'],
        'payment-methods' => ['Catálogos', 'métodos de pago', 'el método de pago'],
        'taxes' => ['Catálogos', 'impuestos', 'el impuesto'],
        'car-statuses' => ['Catálogos', 'estados de coche', 'el estado de coche'],
        'countries' => ['Ubicaciones', 'países', 'el país'],
        'states' => ['Ubicaciones', 'estados', 'el estado'],
        'cities' => ['Ubicaciones', 'ciudades', 'la ciudad'],
        'branches' => ['Ubicaciones', 'sucursales', 'la sucursal'],
        'sales' => ['Ventas', 'ventas', 'la venta'],
        'reports' => ['Reportes', 'reportes', 'el reporte'],
        'users' => ['Usuarios', 'usuarios', 'el usuario'],
        'roles' => ['Usuarios', 'roles', 'el rol'],
        'permissions' => ['Usuarios', 'permisos', 'el permiso'],
        'audit-logs' => ['Auditoría', 'registros de auditoría', 'el registro de auditoría'],
        'stats' => ['Estadísticas', 'estadísticas', 'la estadística'],
    ];

    /**
     * Resúmenes que no siguen la convención `recurso.acción`.
     *
     * @var array<string, string>
     */
    private const SUMMARIES = [
        'auth/login' => 'Iniciar sesión',
        'auth/me' => 'Consultar el perfil del usuario autenticado',
        'auth/password' => 'Cambiar la contraseña propia',
        'auth/logout' => 'Cerrar sesión',
        'auth/refresh' => 'Renovar el token de acceso',
        'reports/cars' => 'Reporte de inventario de coches',
        'reports/cars/pdf' => 'Descargar el reporte de coches en PDF',
        'reports/sales/pdf' => 'Descargar el reporte de ventas en PDF',
        'sales/{sale}/cancel' => 'Cancelar una venta',
        'users/{user}/reset-password' => 'Restablecer la contraseña de un usuario',
        'audit-logs/export' => 'Exportar registros de auditoría en CSV',
        'stats/summary' => 'Resumen de KPIs del negocio',
        'stats/top-users' => 'Ranking de vendedores',
        'stats/top-branches' => 'Ranking de sucursales',
        'stats/top-cities' => 'Ranking de ciudades',
        'stats/top-brands' => 'Ranking de marcas',
        'stats/top-models' => 'Ranking de modelos',
        'stats/inventory' => 'Resumen del inventario actual',
        'stats/sales/trend' => 'Tendencia mensual de ventas',
    ];

    /**
     * Completa el documento con la licencia del proyecto.
     */
    public static function document(OpenApi $document): void
    {
        $document->info->setLicense(new License('MIT', 'MIT'));
    }

    /**
     * Grupos con su orden de aparición en la documentación.
     *
     * @return list<Tag>
     */
    public static function tags(): array
    {
        $tags = [];

        foreach (self::TAGS as $name => [$weight, $description]) {
            $tag = new Tag($name, $description);
            $tag->setAttribute('weight', $weight);

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Completa la operación con grupo, resumen, parámetros y errores.
     */
    public static function transform(Operation $operation, RouteInfo $routeInfo): void
    {
        $path = Str::after($routeInfo->route->uri(), 'api/');
        $resource = Str::before($path, '/');
        $definition = self::RESOURCES[$resource] ?? null;

        if ($definition !== null) {
            $operation->setTags([$definition[0]]);
        }

        $operation->summary = self::SUMMARIES[$path]
            ?? self::summary($routeInfo->method, $path, $definition);

        ApiDocumentationParameters::apply($operation);
        ApiDocumentationResponses::documentErrors($operation, $routeInfo->route, $path);
        ApiDocumentationResponses::documentBinary($operation, $path);
    }

    /**
     * @param  array{string, string, string}|null  $definition
     */
    private static function summary(string $method, string $path, ?array $definition): string
    {
        if ($definition === null) {
            return 'Operación sobre '.$path;
        }

        [, $plural, $singular] = $definition;

        $isCollection = ! Str::contains($path, '/');

        return match ($method) {
            'get' => $isCollection ? "Listar {$plural}" : "Ver {$singular}",
            'post' => "Crear {$singular}",
            'put', 'patch' => "Actualizar {$singular}",
            'delete' => "Eliminar {$singular}",
            default => "Operación sobre {$plural}",
        };
    }
}
