<?php

declare(strict_types=1);

namespace App\DTOs\Cars;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class CarModelData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?string $description = null, public ?int $brandId = null, public ?bool $isActive = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), description: self::string($request, 'description'), brandId: self::integer($request, 'brand_id'), isActive: self::boolean($request, 'is_active'));
    }
}
