<?php

declare(strict_types=1);

namespace App\Exceptions\Users;

use Exception;

class LastAdminException extends Exception
{
    public static function cannotRemoveLastActiveAdmin(): self
    {
        return new self('The operation cannot be completed because the system must keep at least one active administrator.');
    }
}
