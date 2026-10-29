<?php

declare(strict_types=1);

namespace App\Http\Resources\Locations;

use App\Models\Locations\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin City
 */
class CityResource extends JsonResource
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
            'state_id' => $this->state_id,
            'state' => new StateResource($this->whenLoaded('state')),
            'is_active' => $this->is_active,
        ];
    }
}
