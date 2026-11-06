<?php

declare(strict_types=1);

namespace App\Services\Stats;

/**
 * Rankings de ventas completadas por vendedor, sucursal, ciudad, marca y modelo.
 */
final class StatsRankingService
{
    public function __construct(private readonly StatsQuery $query) {}

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
        $rows = $this->query->sales($startDate, $endDate, $branchId)
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
            'base_currency' => $this->query->baseCurrency(),
            'meta' => $this->query->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'last_name' => (string) $row->last_name,
                'code' => (string) $row->code,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->query->money($row->total_base_amount),
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
        $rows = $this->query->sales($startDate, $endDate, $branchId)
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
            'base_currency' => $this->query->baseCurrency(),
            'meta' => $this->query->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
                'city' => $row->city === null ? null : (string) $row->city,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->query->money($row->total_base_amount),
            ])->all()),
        ];
    }

    /**
     * Ciudades con más ventas completadas y mayor facturación.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null},
     *     data: list<array{id: int, name: string, state: string, country: string, sales_count: int, total_base_amount: float}>
     * }
     */
    public function topCities(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        $rows = $this->query->sales($startDate, $endDate, $branchId)
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->join('cities', 'cities.id', '=', 'branches.city_id')
            ->join('states', 'states.id', '=', 'cities.state_id')
            ->join('countries', 'countries.id', '=', 'states.country_id')
            ->select('cities.id', 'cities.name', 'states.name as state', 'countries.name as country')
            ->selectRaw('COUNT(sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_base_amount), 0) as total_base_amount')
            ->groupBy('cities.id', 'cities.name', 'states.name', 'countries.name')
            ->orderByDesc('sales_count')
            ->orderByDesc('total_base_amount')
            ->orderBy('cities.name')
            ->orderBy('cities.id')
            ->limit($limit)
            ->get();

        return [
            'base_currency' => $this->query->baseCurrency(),
            'meta' => $this->query->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'state' => (string) $row->state,
                'country' => (string) $row->country,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->query->money($row->total_base_amount),
            ])->all()),
        ];
    }

    /**
     * Marcas más vendidas, contando también los coches con soft delete.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null},
     *     data: list<array{id: int, name: string, sales_count: int, total_base_amount: float}>
     * }
     */
    public function topBrands(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        $rows = $this->query->sales($startDate, $endDate, $branchId)
            ->join('cars', 'cars.id', '=', 'sales.car_id')
            ->join('car_models', 'car_models.id', '=', 'cars.car_model_id')
            ->join('brands', 'brands.id', '=', 'car_models.brand_id')
            ->select('brands.id', 'brands.name')
            ->selectRaw('COUNT(sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_base_amount), 0) as total_base_amount')
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc('sales_count')
            ->orderByDesc('total_base_amount')
            ->orderBy('brands.name')
            ->orderBy('brands.id')
            ->limit($limit)
            ->get();

        return [
            'base_currency' => $this->query->baseCurrency(),
            'meta' => $this->query->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->query->money($row->total_base_amount),
            ])->all()),
        ];
    }

    /**
     * Modelos más vendidos con su marca, contando coches con soft delete.
     *
     * @return array{
     *     base_currency: string,
     *     meta: array{limit: int, start_date: string|null, end_date: string|null, branch_id: int|null},
     *     data: list<array{id: int, name: string, brand_id: int, brand_name: string, sales_count: int, total_base_amount: float}>
     * }
     */
    public function topModels(?string $startDate, ?string $endDate, ?int $branchId, int $limit): array
    {
        $rows = $this->query->sales($startDate, $endDate, $branchId)
            ->join('cars', 'cars.id', '=', 'sales.car_id')
            ->join('car_models', 'car_models.id', '=', 'cars.car_model_id')
            ->join('brands', 'brands.id', '=', 'car_models.brand_id')
            ->select('car_models.id', 'car_models.name', 'brands.id as brand_id', 'brands.name as brand_name')
            ->selectRaw('COUNT(sales.id) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total_base_amount), 0) as total_base_amount')
            ->groupBy('car_models.id', 'car_models.name', 'brands.id', 'brands.name')
            ->orderByDesc('sales_count')
            ->orderByDesc('total_base_amount')
            ->orderBy('car_models.name')
            ->orderBy('car_models.id')
            ->limit($limit)
            ->get();

        return [
            'base_currency' => $this->query->baseCurrency(),
            'meta' => $this->query->rankingMeta($startDate, $endDate, $branchId, $limit),
            'data' => array_values($rows->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'brand_id' => (int) $row->brand_id,
                'brand_name' => (string) $row->brand_name,
                'sales_count' => (int) $row->sales_count,
                'total_base_amount' => $this->query->money($row->total_base_amount),
            ])->all()),
        ];
    }
}
