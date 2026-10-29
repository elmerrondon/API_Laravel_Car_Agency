<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Throwable;

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
     * Recursos con borrado lógico: eliminar no puede violar una clave foránea.
     *
     * @var list<string>
     */
    private const SOFT_DELETED = ['cars/{car}', 'users/{user}'];

    /**
     * Descripciones de los parámetros de paginación y de los filtros de reportes.
     *
     * @var array<string, string>
     */
    private const PARAMETERS = [
        'page' => 'Página solicitada, empezando en 1.',
        'per_page' => 'Elementos por página (1-100). Por defecto 15.',
        'from' => 'Fecha inicial en formato `Y-m-d`. Se envía junto con `to`.',
        'to' => 'Fecha final en formato `Y-m-d` (incluida). Se envía junto con `from`.',
        'branch_id' => 'Filtra por sucursal. Sin valor se incluyen todas.',
    ];

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
    ];

    /**
     * Resúmenes que no siguen la convención `recurso.acción`.
     *
     * @var array<string, string>
     */
    private const SUMMARIES = [
        'auth/login' => 'Iniciar sesión',
        'auth/logout' => 'Cerrar sesión',
        'auth/refresh' => 'Renovar el token de acceso',
        'reports/cars' => 'Reporte de inventario de coches',
        'reports/cars/pdf' => 'Descargar el reporte de coches en PDF',
        'reports/sales/pdf' => 'Descargar el reporte de ventas en PDF',
        'sales/{sale}/cancel' => 'Cancelar una venta',
    ];

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

        self::documentParameters($operation);
        self::documentErrors($operation, $routeInfo->route, $path);
        self::documentBinaryResponses($operation, $path);
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

    /**
     * Añade `page` a los listados y describe la paginación y los filtros.
     */
    private static function documentParameters(Operation $operation): void
    {
        $documented = [];

        foreach ($operation->parameters as $parameter) {
            if (! $parameter instanceof Parameter || $parameter->in !== 'query') {
                continue;
            }

            $documented[] = $parameter->name;

            if (isset(self::PARAMETERS[$parameter->name])) {
                $parameter->description = self::PARAMETERS[$parameter->name];
            }
        }

        if (! in_array('per_page', $documented, true) || in_array('page', $documented, true)) {
            return;
        }

        $page = Parameter::make('page', 'query');
        $page->description = self::PARAMETERS['page'];
        $page->setSchema(Schema::fromType((new IntegerType)->setMin(1)));

        $operation->addParameters([$page]);
    }

    /**
     * Los PDF se sirven como binario: quitamos el `application/json` que
     * Scramble infiere del tipo de retorno de Laravel.
     */
    private static function documentBinaryResponses(Operation $operation, string $path): void
    {
        if (! Str::endsWith($path, '/pdf')) {
            return;
        }

        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && (int) $response->code === 200) {
                unset($response->content['application/json']);
            }
        }
    }

    /**
     * Documenta 429, 403 y 409 según el middleware y el tipo de operación.
     */
    private static function documentErrors(Operation $operation, Route $route, string $path): void
    {
        self::addResponse(
            $operation,
            429,
            'Demasiadas peticiones: se superó el límite por minuto.',
        );

        if (self::hasPermissionMiddleware($route)) {
            self::addResponse(
                $operation,
                403,
                'El usuario autenticado no tiene el permiso requerido.',
            );
        }

        if (self::canConflict($path, $operation->method)) {
            self::addResponse(
                $operation,
                409,
                'Conflicto: el estado del recurso o sus referencias impiden la operación.',
            );
        }
    }

    private static function hasPermissionMiddleware(Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && Str::startsWith($middleware, 'permission:')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un borrado con claves foráneas `RESTRICT` o una venta ya cancelada
     * responden 409 desde `bootstrap/app.php`.
     */
    private static function canConflict(string $path, string $method): bool
    {
        if ($path === 'sales/{sale}/cancel') {
            return true;
        }

        return $method === 'delete' && ! in_array($path, self::SOFT_DELETED, true);
    }

    private static function addResponse(Operation $operation, int $code, string $description): void
    {
        if (self::hasResponseCode($operation, $code)) {
            return;
        }

        $schema = (new ObjectType)
            ->addProperty('message', new StringType)
            ->setRequired(['message']);

        $operation->addResponse(
            Response::make($code)
                ->setDescription($description)
                ->setContent('application/json', Schema::fromType($schema)),
        );
    }

    private static function hasResponseCode(Operation $operation, int $code): bool
    {
        foreach ($operation->responses ?? [] as $response) {
            if ($response instanceof Response && (int) $response->code === $code) {
                return true;
            }

            if (! $response instanceof Response) {
                try {
                    if ((int) $response->resolve()->code === $code) {
                        return true;
                    }
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return false;
    }
}
