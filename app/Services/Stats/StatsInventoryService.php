<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;

/**
 * Inventario actual (sin soft deletes) y su desglose por estado y sucursal.
 */
final class StatsInventoryService
{
    public function __construct(private readonly StatsQuery $query) {}

    /**
     * Inventario actual (sin soft deletes) con desglose por estado y sucursal.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{branch_id: int|null},
     *     total_cars: int,
     *     total_value: float,
     *     by_status: list<array{status: string, label: string, count: int, total_value: float}>,
     *     by_branch: list<array{id: int, name: string, code: string, count: int, total_value: float}>
     * }
     */
    public function inventory(?int $branchId): array
    {
        $snapshot = $this->snapshot($branchId);

        $rows = Car::query()
            ->join('branches', 'branches.id', '=', 'cars.branch_id')
            ->when($branchId !== null, fn ($query) => $query->where('cars.branch_id', $branchId))
            ->select('branches.id', 'branches.name', 'branches.code')
            ->selectRaw('COUNT(cars.id) as count')
            ->selectRaw('COALESCE(SUM(cars.price), 0) as total_value')
            ->groupBy('branches.id', 'branches.name', 'branches.code')
            ->orderByDesc('count')
            ->orderBy('branches.name')
            ->orderBy('branches.id')
            ->get();

        return [
            'base_currency' => $this->query->baseCurrency(),
            'meta' => ['branch_id' => $branchId],
            'total_cars' => $snapshot['total_cars'],
            'total_value' => $snapshot['total_value'],
            'by_status' => $snapshot['by_status'],
            'by_branch' => array_values($rows->map(fn (Car $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->getAttribute('name'),
                'code' => (string) $row->getAttribute('code'),
                'count' => (int) $row->getAttribute('count'),
                'total_value' => $this->query->money($row->getAttribute('total_value')),
            ])->all()),
        ];
    }

    /**
     * Inventario actual (sin soft deletes) agrupado por estado.
     *
     * @return array{total_cars: int, total_value: float, by_status: list<array{status: string, label: string, count: int, total_value: float}>}
     */
    public function snapshot(?int $branchId): array
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
                'total_value' => $row === null ? 0.0 : $this->query->money($row->getAttribute('total_value')),
            ];
        }

        return [
            'total_cars' => array_sum(array_column($byStatus, 'count')),
            'total_value' => $this->query->money(array_sum(array_column($byStatus, 'total_value'))),
            'by_status' => $byStatus,
        ];
    }
}
