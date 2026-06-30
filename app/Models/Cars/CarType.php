<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarType extends Model
{
    protected $fillable = ["name","description","is_active"];

    protected $hidden = ["created_at", "updated_at"];

    public function cars(){
        return $this->hasMany(Car::class);
    }

    public function casts() : array {
        return ['is_active' => 'boolean'];
    }
    
}
