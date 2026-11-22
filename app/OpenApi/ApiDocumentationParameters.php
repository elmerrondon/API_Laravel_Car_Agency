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
        'user_id' => 'Filtra por el usuario que realizó la acción o la venta.',
        'action' => 'Filtra por acción auditada, por ejemplo `auth.login` o `sale.created`.',
        'auditable_type' => 'Tipo de recurso auditado: `user`, `role`, `car`, `branch` o `sale`.',
        'auditable_id' => 'Identificador del recurso auditado.',
        'status' => 'Filtra por estado: `available`/`reserved`/`sold` en coches, `completed`/`cancelled` en ventas.',
        'search' => 'Prefijo case-insensitive. Busca en el VIN de coches, en el nombre de catálogos y ubicaciones, y en nombre, apellido, email, código o documento de usuarios. `%`, `_` y `!` se tratan literalmente.',
        'is_active' => 'Filtra por activo (`true`/`false` o `1`/`0`).',
        'is_base' => 'Filtra por moneda base.',
        'brand_id' => 'Filtra por marca.',
        'car_model_id' => 'Filtra por modelo de coche.',
        'car_type_id' => 'Filtra por tipo de coche.',
        'category_id' => 'Filtra por categoría.',
        'color_id' => 'Filtra por color.',
        'country_id' => 'Filtra por país.',
        'state_id' => 'Filtra por estado/provincia.',
        'city_id' => 'Filtra por ciudad.',
        'role_id' => 'Filtra por rol asignado.',
        'payment_method_id' => 'Filtra por método de pago.',
        'payment_currency_id' => 'Filtra por la moneda del pago.',
        'year_min' => 'Año de fabricación mínimo (inclusive).',
        'year_max' => 'Año de fabricación máximo (inclusive).',
        'price_min' => 'Precio mínimo (inclusive).',
        'price_max' => 'Precio máximo (inclusive).',
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
