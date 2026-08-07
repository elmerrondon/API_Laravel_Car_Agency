<?php

namespace App\Models\Users;

use App\Enums\Users\PermissionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    protected $fillable = ["name","description"];

    protected $hidden = ["created_at","updated_at"];

    public function roles(){
        return $this->belongsToMany(Role::class);
    }

    public function casts() : array {
        return [
            'name' => PermissionEnum::class
        ];
    }
}
