<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Cars\CarResource;
use App\Http\Resources\Locations\BranchResource;
use App\Http\Resources\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'exchange_rate' => $this->exchange_rate,
            'base_price' => $this->base_price,
            'taxes' => $this->taxes, 
            'total_taxes' => $this->total_taxes,
            'discount_percentage' => $this->discount_percentage,
            'total_discount' => $this->total_discount,
            'total_base_amount' => $this->total_base_amount,
            'total_amount_paid' => $this->total_amount_paid,
            'status' => $this->status,
            'sale_date' => $this->sale_date,
            'branch_id' => $this->branch_id,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'car_id' => $this->car_id,
            'car' => new CarResource($this->whenLoaded('car')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'payment_currency_id' => $this->payment_currency_id,
            'payment_currency' => new CurrencyResource($this->whenLoaded('paymentCurrency')),
            'base_currency_id' => $this->base_currency_id,
            'base_currency' => new CurrencyResource($this->whenLoaded('baseCurrency')),
            'payment_method_id' => $this->payment_method_id,
            'payment_method' => new PaymentMethodResource($this->whenLoaded('paymentMethod')),
            'notes' => $this->notes
        ];
    }
}
