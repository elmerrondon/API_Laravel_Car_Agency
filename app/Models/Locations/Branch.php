<?php

namespace App\Models\Locations;

use App\Models\Cars\Car;
use App\Models\Sales\Sale;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes;

    protected $fillable = ["name","address","code","description","city_id","zip_Code","is_active"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function city(){
        return $this->belongsTo(City::class);
    }

    public function cars(){
        return $this->hasMany(Car::class);
    }

    public function users(){
        return $this->belongsToMany(User::class);
    }

    public function sales(){
        return $this->hasMany(Sale::class);
    }

    public function casts() : array{
        return ["is_active" => "boolean"];
    }
}
