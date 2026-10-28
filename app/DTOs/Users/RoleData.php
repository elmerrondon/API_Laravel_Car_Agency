<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class RoleData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?string $description = null, public ?array $permissions = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), description: self::string($request, 'description'), permissions: self::list($request, 'permissions'));
    }
}
