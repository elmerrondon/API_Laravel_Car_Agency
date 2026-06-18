<?php 

namespace App\DTOs\Locations;

use Illuminate\Foundation\Http\FormRequest;

readonly class StateData{
    public function __construct(public ?string $name, public ?int $country_id, public ?bool $isActive)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name') ?? null, country_id: $request->validated('country_id') ?? null, isActive: $request->validated('is_active') ?? null);
    }
}