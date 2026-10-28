<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class TaxData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?string $code = null, public ?float $percentage = null, public ?bool $isActive = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), code: self::string($request, 'code'), percentage: self::number($request, 'percentage'), isActive: self::boolean($request, 'is_active'));
    }
}
