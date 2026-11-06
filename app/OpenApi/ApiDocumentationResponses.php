<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Respuestas binarias y de error que Scramble no puede inferir.
 */
final class ApiDocumentationResponses
{
    /**
     * Recursos con borrado lógico: eliminar no puede violar una clave foránea.
     *
     * @var list<string>
     */
    private const SOFT_DELETED = ['cars/{car}', 'users/{user}'];

    /**
     * Los PDF y el CSV se sirven como binario: quitamos el `application/json`
     * que Scramble infiere del tipo de retorno de Laravel.
     */
    public static function documentBinary(Operation $operation, string $path): void
    {
        if (! Str::endsWith($path, ['/pdf', '/export'])) {
            return;
        }

        foreach ($operation->responses ?? [] as $response) {
            if (! $response instanceof Response || (int) $response->code !== 200) {
                continue;
            }

            foreach (array_keys($response->content) as $mediaType) {
                // Scramble añade una variante con charset además de la que
                // declara el atributo `#[Response]`; el contrato queda limpio
                // con un único `text/csv` (o `application/pdf`).
                if ($mediaType === 'application/json' || str_starts_with($mediaType, 'text/csv;')) {
                    unset($response->content[$mediaType]);
                }
            }
        }
    }

    /**
     * Documenta 429, 403 y 409 según el middleware y el tipo de operación.
     */
    public static function documentErrors(Operation $operation, Route $route, string $path): void
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
