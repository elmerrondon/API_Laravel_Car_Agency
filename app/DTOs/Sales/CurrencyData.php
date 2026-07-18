<?php 

namespace App\DTOs\Sales;

use Illuminate\Foundation\Http\FormRequest;

readonly class CurrencyData {
    public function __construct(public ?string $name = null, public ?string $code = null, public ?bool $isBase = null, public ?bool $isActive = null)
    {
       
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name'), code: $request->validated('code'), isBase: $request->validated('is_base'), isActive: $request->validated('is_active'));
    }
}