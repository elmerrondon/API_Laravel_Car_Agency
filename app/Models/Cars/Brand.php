<?php

namespace App\Models\Cars;

use App\Models\Locations\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Brand extends Model
{
    protected $fillable = ["name","description","country_id","is_active"];

    protected $hidden = ["created_at","updated_at"];

    public function country(){
        return $this->belongsTo(Country::class);
    }

    public function models(){
        return $this->hasMany(CarModel::class);
    }

  
    public function casts() : array
    {
        return [
            'is_active' => 'boolean'
        ];
    }
}
