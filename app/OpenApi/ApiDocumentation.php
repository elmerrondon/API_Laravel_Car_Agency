<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
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
     * Completa la operación con los errores que la aplicación puede devolver.
     */
    public static function transform(Operation $operation, RouteInfo $routeInfo): void
    {
        $path = Str::after($routeInfo->route->uri(), 'api/');

        self::documentParameters($operation);
        self::documentErrors($operation, $routeInfo->route, $path);
        self::documentBinaryResponses($operation, $path);
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
