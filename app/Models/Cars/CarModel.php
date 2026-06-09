<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarModel extends Model
{
    use SoftDeletes;

    protected $fillable = ["name","description","brand_id"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function brand(){
        return $this->belongsTo(Brand::class);
    }

    public function cars(){
        return $this->hasMany(Car::class);
    }
}
