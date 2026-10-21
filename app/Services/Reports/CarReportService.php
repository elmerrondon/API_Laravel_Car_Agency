<?php

namespace App\Services\Reports;

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use Illuminate\Support\Facades\DB;

class CarReportService
{
    /**
     * Build the car inventory report grouped by type, brand and status.
     *
     * @return array{
     *     filters: array{branch_id: int|null},
     *     branch: string|null,
     *     total_cars: int,
     *     total_price: float,
     *     base_currency: string,
     *     by_type: list<array{name: string, count: int, total_price: float}>,
     *     by_brand: list<array{name: string, count: int, total_price: float}>,
     *     by_status: list<array{status: string, label: string, count: int, total_price: float}>
     * }
     */
    public function build(?int $branchId = null): array
    {
        $byType = $this->groupByRelation($branchId, 'car_types', 'cars.car_type_id');
        $byBrand = $this->groupByBrand($branchId);

        $byStatus = Car::query()
            ->when($branchId !== null, fn ($query) => $query->where('cars.branch_id', $branchId))
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(price), 0) as total_price'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status->value,
                'label' => $this->statusLabel($row->status),
                'count' => (int) $row->count,
                'total_price' => round((float) $row->total_price, 2),
            ])
            ->values()
            ->all();

        return [
            'filters' => ['branch_id' => $branchId],
            'branch' => $branchId !== null ? Branch::find($branchId)?->name : null,
            'total_cars' => array_sum(array_column($byStatus, 'count')),
            'total_price' => round(array_sum(array_column($byStatus, 'total_price')), 2),
            'base_currency' => Currency::where('is_base', true)->value('code') ?? 'USD',
            'by_type' => $byType,
            'by_brand' => $byBrand,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Group cars by a catalog table joined through the given foreign key.
     *
     * @return list<array{name: string, count: int, total_price: float}>
     */
    private function groupByRelation(?int $branchId, string $table, string $foreignKey): array
    {
        return Car::query()
            ->join($table, "{$table}.id", '=', $foreignKey)
            ->when($branchId !== null, fn ($query) => $query->where('cars.branch_id', $branchId))
            ->select("{$table}.name as name", DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(cars.price), 0) as total_price'))
            ->groupBy("{$table}.name")
            ->orderByDesc('count')
            ->orderBy("{$table}.name")
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'total_price' => round((float) $row->total_price, 2),
            ])
            ->all();
    }

    /**
     * Group cars by the brand of their model.
     *
     * @return list<array{name: string, count: int, total_price: float}>
     */
    private function groupByBrand(?int $branchId): array
    {
        return Car::query()
            ->join('car_models', 'car_models.id', '=', 'cars.car_model_id')
            ->join('brands', 'brands.id', '=', 'car_models.brand_id')
            ->when($branchId !== null, fn ($query) => $query->where('cars.branch_id', $branchId))
            ->select('brands.name as name', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(cars.price), 0) as total_price'))
            ->groupBy('brands.name')
            ->orderByDesc('count')
            ->orderBy('brands.name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'count' => (int) $row->count,
                'total_price' => round((float) $row->total_price, 2),
            ])
            ->all();
    }

    private function statusLabel(CarStatus $status): string
    {
        return match ($status) {
            CarStatus::AVAILABLE => 'Disponible',
            CarStatus::RESERVED => 'Reservado',
            CarStatus::SOLD => 'Vendido',
        };
    }
}
