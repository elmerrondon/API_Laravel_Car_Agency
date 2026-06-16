<?php

namespace App\Models\Locations;

use App\Models\Cars\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    protected $hidden = ['created_at','updated_at','created_at'];

   public function states(){
      return $this->hasMany(State::class);
   }

   public function brands(){
      return $this->hasMany(Brand::class);
   }

   public function cities(){
      return $this->hasManyThrough(City::class, State::class);
   }
}
