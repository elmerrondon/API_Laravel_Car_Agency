<?php

declare(strict_types=1);

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['name', 'code', 'is_base', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_base' => 'boolean'];
    }
}
