<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
