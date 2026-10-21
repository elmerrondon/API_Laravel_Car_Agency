<?php

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;
use Override;

class Country extends Model
{
    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    #[Override]
    public function casts()
    {
        return ['is_active' => 'boolean'];
    }
}
