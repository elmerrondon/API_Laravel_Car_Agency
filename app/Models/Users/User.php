<?php

declare(strict_types=1);

namespace App\Models\Users;

use App\Enums\Users\PermissionEnum;
use App\Models\Locations\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    private const PERMISSION_CACHE_PREFIX = 'permissions:user:';

    protected $fillable = [
        'name',
        'last_name',
        'document_number',
        'code',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Indica si alguno de los roles del usuario concede el permiso indicado.
     */
    public function hasPermissionTo(PermissionEnum $permission): bool
    {
        return in_array($permission->value, $this->permissionNames(), true);
    }

    /**
     * Nombres de los permisos efectivos del usuario, cacheados unos minutos
     * para no repetir el join en cada petición.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $ttl = (int) config('permissions.cache_ttl');

        if ($ttl <= 0) {
            return $this->queryPermissionNames();
        }

        /** @var list<string> $names */
        $names = Cache::remember(
            $this->permissionCacheKey((int) $this->getKey()),
            now()->addSeconds($ttl),
            fn (): array => $this->queryPermissionNames(),
        );

        return $names;
    }

    /**
     * Descarta la entrada de caché del usuario.
     */
    public function forgetPermissionCache(): void
    {
        Cache::forget($this->permissionCacheKey((int) $this->getKey()));
    }

    /**
     * Descarta la caché de todos los usuarios que tienen el rol indicado.
     */
    public static function flushPermissionCacheForRole(int $roleId): void
    {
        self::query()
            ->whereHas('roles', fn (Builder $query) => $query->whereKey($roleId))
            ->pluck('id')
            ->each(fn (int $userId) => Cache::forget(self::PERMISSION_CACHE_PREFIX.$userId));
    }

    /**
     * Consulta los permisos en base de datos (sin caché).
     *
     * @return list<string>
     */
    private function queryPermissionNames(): array
    {
        /** @var Collection<int, Role> $roles */
        $roles = $this->roles()->with('permissions:id,name')->get();

        $names = [];

        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                // El modelo Permission castea `name` a PermissionEnum.
                $names[] = $permission->name->value;
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    private static function permissionCacheKey(int $userId): string
    {
        return self::PERMISSION_CACHE_PREFIX.$userId;
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
