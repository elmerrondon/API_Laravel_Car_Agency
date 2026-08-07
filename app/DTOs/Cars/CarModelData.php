<?php 

namespace App\DTOs\Cars;

use Illuminate\Foundation\Http\FormRequest;

readonly class CarModelData{
    public function __construct(public ?string $name = null, public ?string $description = null, public ?int $brandId = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) :  self{
        return new self(name: $request->validated('name'),description: $request->validated('description'), brandId: $request->validated('brand_id'), isActive: $request->validated('is_active'));
    }
}