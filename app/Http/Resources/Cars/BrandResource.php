<?php

namespace App\Http\Resources\Cars;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name, 
            'description' => $this->description,
            'country_id' => $this->country_id,
            'is_active' => $this->is_active 
            ];
    }
}
