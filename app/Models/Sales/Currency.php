<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Currency extends Model
{
    use SoftDeletes;

    protected $fillable = ["name","code","is_active","is_base"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function baseSales(){
        return $this->hasMany(Sale::class, "base_currency_id");
    }

    public function paymentSales(){
        return $this->hasMany(Sale::class, "payment_currency_id");
    }

    
    public function casts() : array
    {
        return ["is_active" => "boolean", "is_base" => "boolean"];
    }
}
