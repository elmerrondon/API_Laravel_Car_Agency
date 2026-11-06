<?php

declare(strict_types=1);

namespace App\Http\Controllers\Stats;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stats\InventoryStatsRequest;
use App\Http\Requests\Stats\RankingRequest;
use App\Http\Requests\Stats\StatsFilterRequest;
use App\Http\Requests\Stats\TrendStatsRequest;
use App\Services\Stats\StatsInventoryService;
use App\Services\Stats\StatsRankingService;
use App\Services\Stats\StatsSummaryService;

class StatsController extends Controller
{
    public function __construct(
        private readonly StatsSummaryService $summaryService,
        private readonly StatsInventoryService $inventoryService,
        private readonly StatsRankingService $rankingService,
    ) {}

    public function summary(StatsFilterRequest $request)
    {
        return response()->json(
            $this->summaryService->summary(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
            ),
        );
    }

    public function salesTrend(TrendStatsRequest $request)
    {
        return response()->json(
            $this->summaryService->salesTrend(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
            ),
        );
    }

    public function inventory(InventoryStatsRequest $request)
    {
        return response()->json($this->inventoryService->inventory($request->branchId()));
    }

    public function topBrands(RankingRequest $request)
    {
        return response()->json(
            $this->rankingService->topBrands(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
                $request->limit(),
            ),
        );
    }

    public function topCities(RankingRequest $request)
    {
        return response()->json(
            $this->rankingService->topCities(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
                $request->limit(),
            ),
        );
    }

    public function topBranches(RankingRequest $request)
    {
        return response()->json(
            $this->rankingService->topBranches(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
                $request->limit(),
            ),
        );
    }

    public function topModels(RankingRequest $request)
    {
        return response()->json(
            $this->rankingService->topModels(
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
            $this->rankingService->topUsers(
                $request->startDate(),
                $request->endDate(),
                $request->branchId(),
                $request->limit(),
            ),
        );
    }
}
