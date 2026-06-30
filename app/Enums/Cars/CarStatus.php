<?php

namespace App\Enums\Cars;

enum CarStatus : string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
}
