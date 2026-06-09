<?php

namespace App\Models\Users;

use App\Models\Locations\Branch;
use App\Models\Sales\Sale;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;


    protected $fillable = [
        'name',
        'lastname',
        'document_number',
        'code',
        'email',
        'password',
    ];

  
    protected $hidden = [
        'password',
        'created_at',
        'updated_at',
        'deleted_at'
    ];


    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }


    public function roles(){
        return $this->belongsToMany(Role::class);
    }

    public function branchs(){
        return $this->belongsToMany(Branch::class);
    }

    public function sales(){
        return $this->hasMany(Sale::class);
    }
}
