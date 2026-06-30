<?php 

namespace App\DTOs\Cars;

use Illuminate\Foundation\Http\FormRequest;

readonly class BrandData{
    public function __construct(public ?string $name =  null, public ?string $description = null, public ?int $countryId = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name'), description: $request->validated('description'), countryId: $request->validated('country_id'), isActive: $request->validated('is_active'));
    }
}