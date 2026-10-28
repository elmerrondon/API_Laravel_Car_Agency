<?php

declare(strict_types=1);

namespace App\DTOs\Cars;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class ColorData
{
    use NormalizesRequestValues;

    public function __construct(public ?string $name = null, public ?bool $isActive = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(name: self::string($request, 'name'), isActive: self::boolean($request, 'is_active'));
    }
}
