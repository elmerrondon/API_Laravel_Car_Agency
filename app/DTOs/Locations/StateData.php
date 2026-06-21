<?php 

namespace App\DTOs\Locations;

use Illuminate\Foundation\Http\FormRequest;

readonly class StateData{
    public function __construct(public ?string $name = null, public ?int $country_id = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name') ?? null, country_id: $request->validated('country_id') ?? null, isActive: $request->validated('is_active') ?? null);
    }
}