<?php 

namespace App\DTOs\Cars;

use Illuminate\Foundation\Http\FormRequest;

readonly class CarTypeData{
    public function __construct(public ?string $name = null, public ?string $description = null, public ?bool $isActive = null)
    {

    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name'), description: $request->validated('description'), isActive: $request->validated('is_active'));
    }
}