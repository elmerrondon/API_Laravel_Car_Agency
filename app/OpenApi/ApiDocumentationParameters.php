<?php

declare(strict_types=1);

namespace App\OpenApi;

use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;

/**
 * Descripciones de los parámetros de paginación y de los filtros.
 */
final class ApiDocumentationParameters
{
    /**
     * @var array<string, string>
     */
    private const PARAMETERS = [
        'page' => 'Página solicitada, empezando en 1.',
        'per_page' => 'Elementos por página (1-100). Por defecto 15.',
        'from' => 'Fecha inicial en formato `Y-m-d`. Se envía junto con `to`.',
        'to' => 'Fecha final en formato `Y-m-d` (incluida). Se envía junto con `from`.',
        'start_date' => 'Fecha inicial en formato `Y-m-d`. Se envía junto con `end_date`.',
        'end_date' => 'Fecha final en formato `Y-m-d` (incluida). Se envía junto con `start_date`.',
        'limit' => 'Número máximo de filas del ranking (1-100). Por defecto 10.',
        'branch_id' => 'Filtra por sucursal. Sin valor se incluyen todas.',
        'user_id' => 'Filtra por el usuario que realizó la acción.',
        'action' => 'Filtra por acción auditada, por ejemplo `auth.login` o `sale.created`.',
        'auditable_type' => 'Tipo de recurso auditado: `user`, `role`, `car`, `branch` o `sale`.',
        'auditable_id' => 'Identificador del recurso auditado.',
    ];

    /**
     * Añade `page` a los listados y describe la paginación y los filtros.
     */
    public static function apply(Operation $operation): void
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
}
