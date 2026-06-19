<?php 

namespace App\DTOs\Locations;

use Illuminate\Foundation\Http\FormRequest;

readonly class BranchData {
    
  public function __construct(public ?string $name = null, public ?string $address = null, public ?string $description = null, public ?string $zipCode = null, public ?bool $isActive = null, public ?int $city_id = null)
  {
      
  }

  public static function fromRequest(FormRequest $request) : self{
       return new self(name: $request->validated('name') ?? null, address: $request->validated('address') ?? null, description: $request->validated('description') ?? null, zipCode: $request->validated('zip_code') ?? null, isActive: $request->validated('is_active') ?? null, city_id: $request->validated('city_id') ?? null);
  }
}