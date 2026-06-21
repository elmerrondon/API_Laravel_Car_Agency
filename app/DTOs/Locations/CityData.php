<?php 

namespace App\DTOs\Locations;

use Illuminate\Foundation\Http\FormRequest;

readonly class CityData{
    public function __construct(public ?string $name = null, public ?int $state_id = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self {
        return new self(name: $request->validated('name') ?? null, state_id: $request->validated('state_id') ?? null, isActive: $request->validated('is_active') ?? null);
    }
}