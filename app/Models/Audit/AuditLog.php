<?php

declare(strict_types=1);

namespace App\Models\Audit;

use App\Enums\Audit\AuditAction;
use App\Models\Users\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro inmutable de una acción. Solo `AuditService` escribe en esta tabla.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * La tabla es de solo lectura desde la aplicación: toda escritura pasa por
     * `AuditService`, nunca por asignación masiva desde una petición.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
