<?php 

namespace App\DTOs\Locations;

use Illuminate\Foundation\Http\FormRequest;

readonly class CountryData{
    public function __construct(public ?string $name = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request): self{
        return new self(name: $request->validated('name') ?? null, isActive: $request->validated('is_active') ?? null);
    }
}