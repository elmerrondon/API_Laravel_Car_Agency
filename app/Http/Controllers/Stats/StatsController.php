<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stats\StatsFilterRequest;
use App\Services\Stats\StatsService;

class StatsController extends Controller
{
    public function __construct(private readonly StatsService $service) {}

    public function summary(StatsFilterRequest $request)
    {
        return response()->json(
            $this->service->summary(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
            ),
        );
    }
}
