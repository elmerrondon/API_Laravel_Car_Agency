<?php 

namespace App\Http\Controllers\Cars;

use App\Services\Cars\CarStatusService;

class CarStatusController {
    public function index(CarStatusService $service){
        return response()->json(['data' => $service->getAllStatuses()]);
    }
}