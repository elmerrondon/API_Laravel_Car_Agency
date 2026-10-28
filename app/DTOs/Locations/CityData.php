<?php

declare(strict_types=1);

namespace App\DTOs\Locations;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class CityData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?int $state_id = null, public ?bool $isActive = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), state_id: self::integer($request, 'state_id'), isActive: self::boolean($request, 'is_active'));
    }
}
