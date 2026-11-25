<?php

declare(strict_types=1);

namespace App\DTOs\Locations;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class StateData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?int $country_id = null, public ?bool $isActive = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), country_id: self::integer($request, 'country_id'), isActive: self::boolean($request, 'is_active'));
    }
}
