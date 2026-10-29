<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cars;

use App\Http\Controllers\Controller;
use App\Services\Cars\CarStatusService;

class CarStatusController extends Controller
{
    public function __construct(private readonly CarStatusService $service) {}

    public function index()
    {
        return response()->json(['data' => $this->service->getAllStatuses()]);
    }
}
