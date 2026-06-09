<?php

namespace App\Models\Sales;

use App\Models\Cars\Car;
use App\Models\Locations\Branch;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes;

    protected $fillable = [
     "exchange_rate",
     "base_price",
     "tax_breakdown",
     "total_taxes",
     "discount",
     "total_base_amount",
     "total_amount_paid",
     "status",
     "sale_date",
     "car_id",
     "user_id",
     "branch_id",
     "payment_currency_id",
     "base_currency_id",
     "payment_method_id"
    ];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function car(){
        return $this->belongsTo(Car::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function branch(){
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod(){
        return $this->belongsTo(PaymentMethod::class);
    }

    public function paymentCurrency(){
        return $this->belongsTo(Currency::class, "payment_currency_id");
    }

    public function baseCurrency(){
        return $this->belongsTo(Currency::class, "base_currency_id");
    }

    public function casts() : array{
        return ["tax_breakdown" => "array"];
    }
}
