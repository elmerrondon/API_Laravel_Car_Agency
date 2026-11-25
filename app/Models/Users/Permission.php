<?php

declare(strict_types=1);

namespace App\Models\Users;

use App\Enums\Users\PermissionEnum;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'description'];

    protected $hidden = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'name' => PermissionEnum::class,
        ];
    }
}
