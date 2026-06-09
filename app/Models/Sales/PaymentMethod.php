<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = ["name","is_active"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function sales(){
        return $this->hasMany(Sale::class);
    }

    public function casts() : array{
        return ["is_active" => "boolean"];
    }
    
}
