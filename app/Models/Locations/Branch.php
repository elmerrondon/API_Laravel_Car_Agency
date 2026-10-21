<?php

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['name', 'address', 'code', 'description', 'city_id', 'zip_code', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
