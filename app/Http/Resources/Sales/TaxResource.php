<?php

declare(strict_types=1);

namespace App\Http\Resources\Sales;

use App\Models\Sales\Tax;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tax
 */
class TaxResource extends JsonResource
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
            'percentage' => $this->percentage,
            'is_active' => $this->is_active,
        ];
    }
}
