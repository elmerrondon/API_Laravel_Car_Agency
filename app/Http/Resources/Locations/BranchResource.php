<?php

namespace App\Http\Resources\Locations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
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
            'code' => $this->code,
            'address' => $this->address,
            'description' => $this->description,
            'zip_code' => $this->zip_code,
            'is_active' => $this->is_active,
            'city_id' => $this->city_id,
            'city' => new CityResource($this->whenLoaded('city'))
        ];
    }
}
