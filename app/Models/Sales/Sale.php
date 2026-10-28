<?php

declare(strict_types=1);

namespace App\Models\Sales;

use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'exchange_rate',
        'base_price',
        'taxes',
        'total_taxes',
        'discount_percentage',
        'total_discount',
        'total_base_amount',
        'total_amount_paid',
        'status',
        'sale_date',
        'car_id',
        'user_id',
        'branch_id',
        'payment_currency_id',
        'base_currency_id',
        'payment_method_id',
        'notes',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function paymentCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'payment_currency_id');
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    protected function casts(): array
    {
        return ['taxes' => 'array', 'status' => SaleStatus::class];
    }
}
