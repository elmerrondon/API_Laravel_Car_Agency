<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Users\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsService
{
    /**
     * Consulta base de ventas computables: solo `completed`, con filtros
     * sargables por fecha y sucursal.
     */
    private function salesQuery(?string $startDate, ?string $endDate, ?int $branchId): Builder
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
        $sales = $this->salesQuery($startDate, $endDate, $branchId)
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total_base_amount), 0) as total_base_amount')
            ->first();

        $salesCount = (int) $sales->sales_count;
        $totalBaseAmount = $this->money($sales->total_base_amount);

        $inventory = $this->inventorySnapshot($branchId);

        return [
            'base_currency' => $this->baseCurrency(),
            'meta' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'branch_id' => $branchId,
            ],
            'sales' => [
                'completed_count' => $salesCount,
                'total_base_amount' => $totalBaseAmount,
                'average_ticket' => $salesCount === 0 ? 0.0 : $this->money($totalBaseAmount / $salesCount),
            ],
            'inventory' => $inventory,
            'active_users' => User::query()->where('is_active', true)->count(),
            'active_branches' => Branch::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Inventario actual (sin soft deletes) agrupado por estado.
     *
     * @return array{total_cars: int, total_value: float, by_status: list<array{status: string, label: string, count: int, total_value: float}>}
     */
    private function inventorySnapshot(?int $branchId): array
    {
        $rows = Car::query()
            ->when($branchId !== null, fn ($query) => $query->where('cars.branch_id', $branchId))
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(price), 0) as total_value')
            ->groupBy('status')
            ->get()
            ->keyBy(fn (Car $car): string => $car->status->value);

        $byStatus = [];

        foreach (CarStatus::cases() as $status) {
            $row = $rows->get($status->value);
            $count = $row === null ? 0 : (int) $row->getAttribute('count');

            $byStatus[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => $count,
                'total_value' => $row === null ? 0.0 : $this->money($row->getAttribute('total_value')),
            ];
        }

        return [
            'total_cars' => array_sum(array_column($byStatus, 'count')),
            'total_value' => $this->money(array_sum(array_column($byStatus, 'total_value'))),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Vendedores con más ventas completadas y mayor facturación.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null},
     *     data: list<array{id: int, name: string, last_name: string, code: string, sales_count: int, total_base_amount: float}>
     * }
     */
    public function topUsers(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        $rows = $this->salesQuery($startDate, $endDate, $branchId)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->select('users.id', 'users.name', 'users.last_name', 'users.code')
            ->selectRaw('COUNT(sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_base_amount), 0) as total_base_amount')
            ->groupBy('users.id', 'users.name', 'users.last_name', 'users.code')
            ->orderByDesc('sales_count')
            ->orderByDesc('total_base_amount')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->limit($limit)
            ->get();

        return [
            'base_currency' => $this->baseCurrency(),
            'meta' => $this->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'last_name' => (string) $row->last_name,
                'code' => (string) $row->code,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->money($row->total_base_amount),
            ])->all()),
        ];
    }

    /**
     * Sucursales con más ventas completadas y mayor facturación.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null},
     *     data: list<array{id: int, name: string, code: string, city: string|null, sales_count: int, total_base_amount: float}>
     * }
     */
    public function topBranches(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        $rows = $this->salesQuery($startDate, $endDate, $branchId)
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->leftJoin('cities', 'cities.id', '=', 'branches.city_id')
            ->select('branches.id', 'branches.name', 'branches.code', 'cities.name as city')
            ->selectRaw('COUNT(sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_base_amount), 0) as total_base_amount')
            ->groupBy('branches.id', 'branches.name', 'branches.code', 'cities.name')
            ->orderByDesc('sales_count')
            ->orderByDesc('total_base_amount')
            ->orderBy('branches.name')
            ->orderBy('branches.id')
            ->limit($limit)
            ->get();

        return [
            'base_currency' => $this->baseCurrency(),
            'meta' => $this->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
                'city' => $row->city === null ? null : (string) $row->city,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->money($row->total_base_amount),
            ])->all()),
        ];
    }

    /**
     * @return array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null}
     */
    private function rankingMeta(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        return [
            'limit' => $limit,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'branch_id' => $branchId,
        ];
    }

    private function baseCurrency(): string
    {
        return (string) (Currency::query()->where('is_base', true)->value('code') ?? 'USD');
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
