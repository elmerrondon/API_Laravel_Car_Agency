<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stats\RankingRequest;
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

    public function topBranches(RankingRequest $request)
    {
        return response()->json(
            $this->service->topBranches(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
                $request->limit(),
            ),
        );
    }

    public function topUsers(RankingRequest $request)
    {
        return response()->json(
            $this->service->topUsers(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
                $request->limit(),
            ),
        );
    }
}
