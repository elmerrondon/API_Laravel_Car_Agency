<?php

declare(strict_types=1);

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
