<?php

namespace App\Models\Users;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'description'];

    protected $hidden = ['created_at', 'updated_at'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
}
