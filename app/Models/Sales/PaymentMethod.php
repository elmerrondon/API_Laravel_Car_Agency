<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
