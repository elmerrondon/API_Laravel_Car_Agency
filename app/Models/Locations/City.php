<?php

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class City extends Model
{

    protected $fillable = ["name","state_id","is_active"];

    protected $hidden = ["created_at","updated_at"];

    public function state(){
        return $this->belongsTo(State::class);
    }

    public function branches(){
        return $this->hasMany(Branch::class);
    }

    #[Override]
    public function casts()
    {
        return ["is_active" => "boolean"];
    }
}
