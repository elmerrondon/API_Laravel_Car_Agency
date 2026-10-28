<?php

declare(strict_types=1);

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarModel extends Model
{
    protected $fillable = ['name', 'description', 'brand_id', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
