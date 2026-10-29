<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\SaleReportRequest;
use App\Services\Reports\SaleReportService;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleReportController extends Controller
{
    public function __construct(private readonly SaleReportService $service) {}

    public function pdf(SaleReportRequest $request)
    {
        $report = $this->service->build($request->validated());

        return Pdf::loadView('reports.sales-pdf', $report)
            ->setPaper('a4', 'landscape')
            ->download('reporte-ventas-'.now()->format('Ymd-His').'.pdf');
    }
}
