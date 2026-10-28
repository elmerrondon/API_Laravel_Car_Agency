<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class UserData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?string $lastName = null, public ?string $documentNumber = null, public ?string $code = null, public ?string $email = null, public ?string $password = null, public ?array $roles = null, public ?array $branches = null, public ?bool $isActive = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), lastName: self::string($request, 'last_name'), documentNumber: self::string($request, 'document_number'), code: self::string($request, 'code'), email: self::string($request, 'email'), password: self::string($request, 'password'), roles: self::list($request, 'roles'), branches: self::list($request, 'branches'), isActive: self::boolean($request, 'is_active'));
    }
}
