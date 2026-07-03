<?php 

namespace App\DTOs\Users;

use Illuminate\Foundation\Http\FormRequest;

readonly class RoleData{
    public function __construct(public ?string $name = null, public ?string $description = null)
    {

    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(name: $request->validated('name'), description: $request->validated('description'));
    }
}