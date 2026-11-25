<?php

declare(strict_types=1);

namespace App\Enums\Sales;

enum SaleStatus: string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
}
