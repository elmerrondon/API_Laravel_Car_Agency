<?php

declare(strict_types=1);

namespace App\Models\Locations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class City extends Model
{
    protected $fillable = ['name', 'state_id', 'is_active'];

    protected $hidden = ['created_at', 'updated_at'];

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    #[Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
