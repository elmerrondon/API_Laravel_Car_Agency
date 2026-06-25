<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarModel extends Model
{
    protected $fillable = ["name","description","brand_id","is_active"];

    protected $hidden = ["created_at","updated_at"];

    public function brand(){
        return $this->belongsTo(Brand::class);
    }

    public function cars(){
        return $this->hasMany(Car::class);
    }

    public function casts() : array {
        return ['is_active' => 'boolean'];
    }
}
