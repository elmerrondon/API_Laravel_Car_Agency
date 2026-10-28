<?php

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class State extends Model
{
    protected $fillable = ['name', 'country_id', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
