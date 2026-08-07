<?php

namespace App\Enums\Sales;

enum SaleStatus : string
{
    case COMPLETED = 'completed';
    case PENDING = 'pending';
    case CANCELLED = 'cancelled';
}
