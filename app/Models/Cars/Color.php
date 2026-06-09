<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Color extends Model
{
    use SoftDeletes;

    protected $fillable = ["name"];

    protected $hidden = ["created_at","updated_at","deleted_adt"];

    public function cars(){
       return $this->hasMany(Car::class);
    }
}
