<?php 

namespace App\DTOs\Locations;

use Illuminate\Foundation\Http\FormRequest;

readonly class CountryData{
    public function __construct(public string $name)
    {
        
    }

    public static function fromRequest(FormRequest $request): self{
        return new self(name: $request->validated('name'));
    }
}