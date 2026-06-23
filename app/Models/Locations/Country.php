<?php

namespace App\Models\Locations;

use App\Models\Cars\Brand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Country extends Model
{

    protected $fillable = ['name','is_active'];

    protected $hidden = ['created_at','updated_at'];

   public function states(){
      return $this->hasMany(State::class);
   }

   public function brands(){
      return $this->hasMany(Brand::class);
   }

   public function cities(){
      return $this->hasManyThrough(City::class, State::class);
   }

   #[Override]
   public function casts()
   {
      return ["is_active" => "boolean"];
   }
}
