<?php

namespace App\Models\Users;

use App\Models\Locations\Branch;
use App\Models\Sales\Sale;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;


    protected $fillable = [
        'name',
        'last_name',
        'document_number',
        'code',
        'email',
        'password',
        'is_active'
    ];

  
    protected $hidden = [
        'password',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function roles(){
        return $this->belongsToMany(Role::class);
    }

    public function branches(){
        return $this->belongsToMany(Branch::class);
    }

    public function sales(){
        return $this->hasMany(Sale::class);
    }

      protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean'
        ];
    }
}
