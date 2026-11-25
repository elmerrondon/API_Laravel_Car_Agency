<?php

declare(strict_types=1);

namespace App\Exceptions\Users;

use Exception;

class RoleInUseException extends Exception
{
    public static function assignedToUsers(): self
    {
        return new self('The role cannot be deleted because it has users assigned.');
    }
}
