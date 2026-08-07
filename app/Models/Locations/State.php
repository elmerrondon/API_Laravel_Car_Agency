<?php

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class State extends Model
{
    protected $fillable = ["name","country_id","is_active"];

    protected $hidden = ["created_at","updated_at"];

    public function country(){
        return $this->belongsTo(Country::class);
    }

    public function cities(){
        return $this->hasMany(City::class);
    }


    public function casts()
    {
        return ["is_active" => "boolean"];
    }
}
