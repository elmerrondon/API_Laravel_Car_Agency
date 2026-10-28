<?php

declare(strict_types=1);

namespace App\DTOs\Locations;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class BranchData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?string $address = null, public ?string $description = null, public ?string $zipCode = null, public ?bool $isActive = null, public ?int $city_id = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), address: self::string($request, 'address'), description: self::string($request, 'description'), zipCode: self::string($request, 'zip_code'), isActive: self::boolean($request, 'is_active'), city_id: self::integer($request, 'city_id'));
    }
}
