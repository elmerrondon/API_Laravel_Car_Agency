<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Locations\Branch;
use App\Models\Sales\Currency;
use App\Models\Sales\Sale;
use Illuminate\Support\Collection;

class SaleReportService
{
    /**
     * Relations needed to render the sales report.
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'car.carModel.brand',
        'car.carType',
        'branch',
        'user',
        'paymentMethod',
        'paymentCurrency',
        'baseCurrency',
    ];

    /**
     * Build the sales report data for the given filters.
     *
     * @param  array{from?: string|null, to?: string|null, branch_id?: int|null}  $filters
     * @return array{
     *     sales: Collection<int, Sale>,
     *     summary: array{count: int, total_base_amount: float, total_taxes: float, total_discount: float},
     *     filters: array{from: string|null, to: string|null, branch_id: int|null},
     *     branch: Branch|null,
     *     base_currency: string
     * }
     */
    public function build(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $branchId = $filters['branch_id'] ?? null;

        $sales = Sale::with(self::RELATIONS)
            ->when($from !== null, fn ($query) => $query->whereDate('sale_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->whereDate('sale_date', '<=', $to))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('sale_date')
            ->get();

        return [
            'sales' => $sales,
            'summary' => [
                'count' => $sales->count(),
                'total_base_amount' => round((float) $sales->sum('total_base_amount'), 2),
                'total_taxes' => round((float) $sales->sum('total_taxes'), 2),
                'total_discount' => round((float) $sales->sum('total_discount'), 2),
            ],
            'filters' => [
                'from' => $from,
                'to' => $to,
                'branch_id' => $branchId,
            ],
            'branch' => $branchId !== null ? Branch::find($branchId) : null,
            'base_currency' => Currency::where('is_base', true)->value('code') ?? 'USD',
        ];
    }
}
