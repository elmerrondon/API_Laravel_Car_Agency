<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
