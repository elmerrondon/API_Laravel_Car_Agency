<?php

namespace App\Http\Resources\Cars;

use App\Http\Resources\Locations\BranchResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
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
            'price' => $this->price,
            'mileage' => $this->mileage,
            'year' => $this->year,
            'vin' => $this->vin,
            'status' => $this->status,
            'color_id' => $this->color_id,
            'color' => new ColorResource($this->whenLoaded('color')),
            'car_model_id' => $this->car_model_id,
            'car_model' => new CarModelResource($this->whenLoaded('car_model')),
            'car_type_id' => $this->car_type_id,
            'car_type' => new CarTypeResource($this->whenLoaded('car_type')),
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch'))
        ];
    }
}
