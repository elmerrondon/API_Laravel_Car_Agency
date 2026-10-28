<?php

declare(strict_types=1);

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'code', 'description', 'city_id', 'zip_code', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
