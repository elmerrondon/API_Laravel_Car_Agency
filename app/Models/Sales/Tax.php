<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = ['name', 'code', 'percentage', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
