<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\Enums\Cars\CarStatus;

class CarStatusService
{
    /**
     * @return list<array{status: string}>
     */
    public function getAllStatuses(): array
    {
        return array_map(function ($status) {
            return [
                'status' => $status->value,
            ];
        }, CarStatus::cases());
    }
}
