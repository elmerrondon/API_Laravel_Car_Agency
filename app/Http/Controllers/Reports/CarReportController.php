<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\CarReportRequest;
use App\Services\Reports\CarReportService;
use Barryvdh\DomPDF\Facade\Pdf;

class CarReportController extends Controller
{
    public function __construct(private readonly CarReportService $service) {}

    public function index(CarReportRequest $request)
    {
        return response()->json($this->service->build($this->branchId($request)));
    }

    public function pdf(CarReportRequest $request)
    {
        $report = $this->service->build($this->branchId($request));

        return Pdf::loadView('reports.cars-pdf', $report)
            ->setPaper('a4')
            ->download('reporte-coches-'.now()->format('Ymd-His').'.pdf');
    }

    private function branchId(CarReportRequest $request): ?int
    {
        $branchId = $request->validated('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }
}
