<?php 

namespace App\Services\Cars;

use App\Enums\Cars\CarStatus;

class CarStatusService{
    public function getAllStatuses() : array {
        return array_map(function ($status) {
            return [
                'status' =>  $status->value
            ];
        }, CarStatus::cases());
    }
}