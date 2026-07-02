<?php 

namespace App\DTOs\Users;

use Illuminate\Foundation\Http\FormRequest;

readonly class UserData{
    public function __construct(public ?string $name = null, public ?string $lastName = null, public ?string $documentNumber = null, public ?string $code = null, public ?string $email = null, public ?string $password = null, public ?bool $isActive = null)
    {
       
    }

    public static function fromRequest(FormRequest $request) : self {
        return new self(name: $request->validated('name'), lastName: $request->validated('lastname'), documentNumber: $request->validated('document_number'), code: $request->validated('code'), email: $request->validated('email'), password: $request->validated('password'), isActive: $request->validated('is_active'));
    }
}