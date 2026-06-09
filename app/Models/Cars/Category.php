<?php

namespace App\Models\Cars;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ["name","description"];

    protected $hidden = ["created_at","updated_at","deleted_at"];

    public function cars(){
        return $this->hasMany(Car::class);
    }
}
