<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Enums\Sales\SaleStatus;
use App\Models\Sales\Currency;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Piezas compartidas por los servicios de estadísticas: consulta base de
 * ventas, moneda base, redondeo y metadatos de los rankings.
 */
final class StatsQuery
{
    /**
     * Consulta base de ventas computables: solo `completed`, con filtros
     * sargables por fecha y sucursal.
     */
    public function sales(?string $startDate, ?string $endDate, ?int $branchId): Builder
    {
        $query = DB::table('sales')
            ->where('sales.status', SaleStatus::COMPLETED->value);

        if ($startDate !== null) {
            $query->where('sales.sale_date', '>=', Carbon::parse($startDate)->startOfDay());
        }

        if ($endDate !== null) {
            $query->where('sales.sale_date', '<', Carbon::parse($endDate)->startOfDay()->addDay());
        }

        if ($branchId !== null) {
            $query->where('sales.branch_id', $branchId);
        }

        return $query;
    }

    public function baseCurrency(): string
    {
        return (string) (Currency::query()->where('is_base', true)->value('code') ?? 'USD');
    }

    public function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    /**
     * @return array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null}
     */
    public function rankingMeta(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        return [
            'limit' => $limit,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'branch_id' => $branchId,
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function trendRange(?string $startDate, ?string $endDate): array
    {
        if ($startDate !== null && $endDate !== null) {
            return [
                Carbon::parse($startDate)->startOfMonth(),
                Carbon::parse($endDate)->startOfMonth(),
            ];
        }

        $end = Carbon::now()->startOfMonth();

        return [$end->copy()->subMonthsNoOverflow(11), $end];
    }

    public function periodExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', sale_date)",
            default => "DATE_FORMAT(sale_date, '%Y-%m')",
        };
    }
}
