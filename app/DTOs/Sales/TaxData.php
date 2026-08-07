<?php 

namespace App\DTOs\Sales;

use Illuminate\Foundation\Http\FormRequest;

readonly class TaxData{
    public function __construct(public ?string $name = null, public ?string $code = null, public ?float $percentage = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name'), code: $request->validated('code'), percentage: $request->validated('percentage'), isActive: $request->validated('is_active'));
    }
}