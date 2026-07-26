<?php

namespace App\Http\Resources\Users;

use App\Http\Resources\Locations\BranchResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'document_number' => $this->document_number,
            'code' => $this->code,
            'email' => $this->email,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'is_active' => $this->is_active
        ];
    }
}
