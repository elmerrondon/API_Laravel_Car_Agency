<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\Locations\Branch;
use App\Models\Users\User;

/**
 * KPIs del periodo y tendencia mensual de ventas.
 */
final class StatsSummaryService
{
    public function __construct(
        private readonly StatsQuery $query,
        private readonly StatsInventoryService $inventory,
    ) {}

    /**
     * KPIs del periodo y foto actual del inventario y de la estructura.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{start_date: string|null, end_date: string|null, branch_id: int|null},
     *     sales: array{completed_count: int, total_base_amount: float, average_ticket: float},
     *     inventory: array{total_cars: int, total_value: float, by_status: list<array{status: string, label: string, count: int, total_value: float}>},
     *     active_users: int,
     *     active_branches: int
     * }
     */
    public function summary(?string $startDate, ?string $endDate, ?int $branchId): array
    {
        $sales = $this->query->sales($startDate, $endDate, $branchId)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total_base_amount), 0) as total_base_amount')
            ->first();

        $salesCount = (int) $sales->sales_count;
        $totalBaseAmount = $this->query->money($sales->total_base_amount);

        return [
            'base_currency' => $this->query->baseCurrency(),
            'meta' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch_id' => $branchId,
            ],
            'sales' => [
                'completed_count' => $salesCount,
                'total_base_amount' => $totalBaseAmount,
                'average_ticket' => $salesCount === 0 ? 0.0 : $this->query->money($totalBaseAmount / $salesCount),
            ],
            'inventory' => $this->inventory->snapshot($branchId),
            'active_users' => User::query()->where('is_active', true)->count(),
            'active_branches' => Branch::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Serie mensual de ventas completadas, con los meses sin datos en cero.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{start_date: string, end_date: string, branch_id: int|null},
     *     data: list<array{period: string, sales_count: int, total_base_amount: float}>
     * }
     */
    public function salesTrend(?string $startDate, ?string $endDate, ?int $branchId): array
    {
        [$start, $end] = $this->query->trendRange($startDate, $endDate);

        $expression = $this->query->periodExpression();

        $rows = $this->query->sales(
            $start->toDateString(),
            $end->copy()->endOfMonth()->toDateString(),
            $branchId,
        )
            ->selectRaw("{$expression} as period")
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total_base_amount), 0) as total_base_amount')
            ->groupByRaw($expression)
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $data = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $period = $cursor->format('Y-m');
            $row = $rows->get($period);

            $data[] = [
                'period' => $period,
                'sales_count' => $row === null ? 0 : (int) $row->sales_count,
                'total_base_amount' => $row === null ? 0.0 : $this->query->money($row->total_base_amount),
            ];

            $cursor->addMonthNoOverflow();
        }

        return [
            'base_currency' => $this->query->baseCurrency(),
            'meta' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'branch_id' => $branchId,
            ],
            'data' => $data,
        ];
    }
}
