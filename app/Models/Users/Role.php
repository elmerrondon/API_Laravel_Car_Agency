<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    protected $fillable = ["name","description"];

    protected $hidden = ["created_at","updated_at"];

   public function users(){
    return $this->belongsToMany(User::class);
   }

   public function permissions(){
    return $this->belongsToMany(Permission::class);
   }
}
