<?php 

namespace App\DTOs\Cars;

use Illuminate\Foundation\Http\FormRequest;

readonly class ColorData{
    public function __construct(public ?string $name = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromData(FormRequest $request) :  self{
        return new self(name: $request->validated('name'), isActive: $request->validated('is_active'));
    }
}