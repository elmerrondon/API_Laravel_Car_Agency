<?php 

namespace App\DTOs\Sales;

use Illuminate\Foundation\Http\FormRequest;

readonly class PaymentMethodData{
    public function __construct(public ?string $name = null, public ?bool $isActive = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name'), isActive: $request->validated('is_active'));
    }
}