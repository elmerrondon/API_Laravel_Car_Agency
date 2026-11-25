<?php

declare(strict_types=1);

namespace App\Http\Requests\Stats;

/**
 * Serie temporal de ventas: rango de fechas y sucursal.
 *
 * Sin fechas, el servicio usa los últimos doce meses.
 */
class TrendStatsRequest extends StatsFilterRequest {}
