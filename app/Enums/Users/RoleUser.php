<?php

namespace App\Enums\Users;

enum RoleUser : string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case SELLER = 'seller';
}
